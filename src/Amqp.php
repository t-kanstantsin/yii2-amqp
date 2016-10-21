<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\log\Logger;


/**
 * AMQP wrapper.
 *
 * @property AMQPStreamConnection $connection AMQP connection.
 * @property AMQPChannel $channel AMQP channel.
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @author Kanstantsin Tsimashenka <t.kanstantsin@gmail.com>
 * @since 2.0
 */
class Amqp extends Component
{
    // topic types
    const TYPE_TOPIC = 'topic';
    const TYPE_DIRECT = 'direct';
    const TYPE_HEADERS = 'headers';
    const TYPE_FANOUT = 'fanout';

    /**
     * @var AMQPStreamConnection
     */
    protected static $ampqConnection;

    /**
     * @var string
     */
    public $host = '127.0.0.1';
    /**
     * @var integer
     */
    public $port = 5672;
    /**
     * @var string
     */
    public $user;
    /**
     * @var string
     */
    public $password;
    /**
     * @var string
     */
    public $vhost = '/';
    /**
     * @var boolean
     */
    public $consumeNoAck = true;
    /**
     * Config of exchanges and queues
     * Format:
     * [
     *      'EXCHANGE-NAME' => [
     *          'exchange-config' => [...],
     *          'queue-array' => [
     *              'QUEUE-NAME' => [
     *                  'queue-config' => [...],
     *                  'queue-bind-config' => [...],
     *                  'basic-consumer-config' => [...],
     *                  'basic-qos-config' => [...],
     *              ],
     *          ],
     *      ],
     * ],
     *
     * @var array
     */
    public $config;
    /**
     * @var string
     */
    public $logComponent;
    /**
     * @var string
     */
    public $logCategory;

    /**
     * @var AMQPChannel[]
     */
    protected $channels = [];

    /**
     * @var \yii\log\Logger
     */
    private $_log;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->user === null) {
            throw new Exception("Parameter 'user' was not set for AMQP connection.");
        }
        if (self::$ampqConnection === null) {
            self::$ampqConnection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
                $this->vhost
            );
        }
        if (mb_strlen($this->logComponent) !== 0) {
            $this->_log = \Yii::$app->{$this->logComponent};
        }
    }

    /**
     * Returns AMQP connection.
     *
     * @return AMQPStreamConnection
     */
    public function getConnection()
    {
        return self::$ampqConnection;
    }

    /**
     * Returns AMQP connection.
     *
     * @param string $channel_id
     * @return AMQPChannel
     */
    public function getChannel($channel_id = null)
    {
        $index = $channel_id ?: 'default';
        if (!array_key_exists($index, $this->channels)) {
            $this->channels[$index] = $this->connection->channel($channel_id);
        }

        return $this->channels[$index];
    }

    /**
     * Sends message to the exchange.
     *
     * @param string $exchange
     * @param string $routingKey
     * @param string|array $message
     * @param null $headers
     * @param array $publishArgs
     */
    public function send(string $exchange, string $routingKey, $message, $headers = null, array $publishArgs = [])
    {
        $this->batchSend($exchange, $routingKey, [$message], $headers, $publishArgs);
    }

    /**
     * Sends multiple messages to the exchange in one batch.
     *
     * @param string $exchange
     * @param string $routingKey
     * @param array $messageArray
     * @param null $headers
     * @param array $publishArgs
     * @throws \yii\base\Exception
     */
    public function batchSend($exchange, $routingKey, array $messageArray, $headers = null, array $publishArgs = [])
    {
        $properties = [];
        $this->applyPropertyHeaders($properties, $headers);

        call_user_func_array([$this->channel, 'exchange_declare'], ConfigHelper::getExchangeDeclareArgs($this->config, $exchange));

        foreach ($messageArray as $message) {
            $message = MessageHelper::prepareMessage($message, $properties);
            call_user_func_array([$this->channel, 'batch_basic_publish'], ConfigHelper::getPublishArgs($message, $exchange, $routingKey, $publishArgs));
        }
        $this->channel->publish_batch();
    }

    /**
     * Sends message to the exchange and waits for answer.
     * @todo: check and fix.
     * @link https://www.rabbitmq.com/amqp-0-9-1-reference.html
     *
     * @param string $exchange
     * @param string $routingKey
     * @param string|array $message
     * @param integer $timeout Timeout in seconds.
     * @return string
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     * @throws \yii\base\Exception
     */
    public function ack($exchange, $routingKey, $message, $timeout)
    {
        list ($queueName) = $this->channel->queue_declare('', false, false, true, false);
        $message = MessageHelper::prepareMessage($message, [
            'reply_to' => $queueName,
        ]);
        // queue name must be used for answer's routing key
        $this->channel->queue_bind($queueName, $exchange, $queueName);

        $response = null;
        $callback = function (AMQPMessage $answer) use ($message, &$response) {
            $response = $answer->body;
        };

        $this->channel->basic_consume($queueName, '', false, false, false, false, $callback);
        $this->channel->basic_publish($message, $exchange, $routingKey);
        while (!$response) {
            // exception will be thrown on timeout
            $this->channel->wait(null, false, $timeout);
        }

        return $response;
    }

    /**
     * Listens the exchange for messages.
     *
     * @param string $exchange
     * @param string $routingKey
     * @param callable $callback
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     */
    public function listen($exchange, $routingKey, callable $callback)
    {
        call_user_func_array([$this->channel, 'exchange_declare'], ConfigHelper::getExchangeDeclareArgs($this->config, $exchange));
        list($queue) = call_user_func_array([$this->channel, 'queue_declare'], ConfigHelper::getQueueDeclareArgs($this->config, $exchange, $routingKey));
        call_user_func_array([$this->channel, 'queue_bind'], ConfigHelper::getQueueBindArgs($this->config, $exchange, $queue, $routingKey));
        call_user_func_array([$this->channel, 'basic_consume'], ConfigHelper::getBasicConsumeArgs($this->config, $exchange, $queue, $callback));
        call_user_func_array([$this->channel, 'basic_qos'], ConfigHelper::getBasicQosArgs($this->config, $exchange, $queue));

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }

        $this->channel->close();
        $this->connection->close();
    }

    /**
     * Listens the queue for messages. It uses already existing queue.
     *
     * @param string $queueName
     * @param callable $callback
     * @param bool $break
     */
    public function listenQueue($queueName, $callback, $break = false)
    {
        while (true) {
            if (($message = $this->channel->basic_get($queueName, $this->consumeNoAck)) instanceof AMQPMessage) {
                $callback($message);
            } else {
                if ($break !== false) {
                    break;
                }
            }
        }

        $this->channel->close();
        $this->connection->close();
    }

    /**
     * @param string $exchange
     * @param AMQPMessage $msg
     * @return interpreter\Interpreter
     * @throws \ErrorException
     */
    public function buildInterpreter(string $exchange, AMQPMessage $msg): interpreter\Interpreter
    {
        $exchangeConfig = $this->getExchangeConfig($exchange);
        $interpreter = ArrayHelper::getValue($exchangeConfig, 'interpreter', null);

        // TODO: log errors.
        if (!class_exists($interpreter)) {
            throw new \ErrorException(sprintf("Interpreter class '%s' was not found.", $interpreter));
        }
        if (!is_subclass_of($interpreter, interpreter\Interpreter::class)) {
            throw new \ErrorException(sprintf("Class '%s' is not correct interpreter class.", $interpreter));
        }

        $actions = array_map(function (array $queueConfig) {
            return ArrayHelper::getValue($queueConfig, 'action');
        }, $exchangeConfig['queue-array']);
        $actions = array_filter($actions);

        $interpreter = new $interpreter($this, [
            'msg' => $msg,
            'actions' => $actions,
        ]);

        return $interpreter;
    }

    /**
     * @param string $exchange
     * @return array
     */
    public function getExchangeConfig(string $exchange): array
    {
        return ArrayHelper::getValue($this->config, $exchange, []);
    }

    /**
     * @param $message
     * @param $level
     * @param $category
     */
    public function log(string $message, $level, string $category = null)
    {
        if (!($this->_log instanceof Logger)) {
            return;
        }

        if ($category === null) {
            $category = $this->logCategory;
        } elseif ($category === false) {
            $category = 'application'; // default value from yii logger.
        }

        $this->_log->log($message, $level, $category);
    }

    /**
     * Applies headers in message properties.
     *
     * @param $properties
     * @param $headers
     */
    protected function applyPropertyHeaders(&$properties, $headers)
    {
        if ($headers !== null) {
            $properties['application_headers'] = $headers;
        }
    }
}
