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
use yii\helpers\Json;


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
     * @var AMQPChannel[]
     */
    protected $channels = [];

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

        call_user_func_array([$this->channel, 'exchange_declare'], $this->getExchangeDeclareArgs($exchange));

        foreach ($messageArray as $message) {
            $message = $this->prepareMessage($message, $properties);
            call_user_func_array([$this->channel, 'batch_basic_publish'], $this->getPublishArgs($message, $exchange, $routingKey, $publishArgs));
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
        $message = $this->prepareMessage($message, [
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
        call_user_func_array([$this->channel, 'exchange_declare'], $this->getExchangeDeclareArgs($exchange));
        list($queue) = call_user_func_array([$this->channel, 'queue_declare'], $this->getQueueDeclareArgs($exchange, $routingKey));
        call_user_func_array([$this->channel, 'queue_bind'], $this->getQueueBindArgs($exchange, $queue, $routingKey));
        call_user_func_array([$this->channel, 'basic_consume'], $this->getBasicConsumeArgs($exchange, $queue, $callback));
        call_user_func_array([$this->channel, 'basic_qos'], $this->getBasicQosArgs($exchange, $queue));

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
     * Returns prepared AMQP message.
     *
     * @param string|array|object $message
     * @param array $properties
     * @return AMQPMessage
     * @throws Exception If message is empty.
     */
    public function prepareMessage($message, $properties = null)
    {
        if ($message === null || $message === '') {
            throw new Exception('AMQP message can not be empty');
        }

        if (is_array($message) || is_object($message)) {
            $message = Json::encode($message);
        }

        return new AMQPMessage($message, $properties);
    }

    /**
     * Applies headers in message properties.
     *
     * @param $properties
     * @param $headers
     */
    public function applyPropertyHeaders(&$properties, $headers)
    {
        if ($headers !== null) {
            $properties['application_headers'] = $headers;
        }
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
     * @param string $exchange
     * @return array
     */
    public function getExchangeDeclareArgs(string $exchange): array
    {
        $config = ArrayHelper::getValue($this->config, [$exchange, 'exchange-config'], []);
        $config = array_replace(ConfigManager::$defaultExchangeArgs, $config);
        $config['exchange'] = $exchange;

        return array_values($config);
    }

    /**
     * @param string $exchange
     * @param string $queue
     * @return array
     */
    public function getQueueDeclareArgs(string $exchange, string $queue): array
    {
        // TODO: take in account that routing key might be different from queue name.
        $config = ArrayHelper::getValue($this->config, [$exchange, 'queue-array', $queue, 'queue-config'], []);
        $config = array_replace(ConfigManager::$defaultQueueArgs, $config);
        $config['queue'] = $queue;

        return array_values($config);
    }

    /**
     * @param string $exchange
     * @param string $queue
     * @param string $routingKey
     * @return array
     */
    public function getQueueBindArgs(string $exchange, string $queue, string $routingKey): array
    {
        // TODO: take in account that routing key might be different from queue name.
        $config = ArrayHelper::getValue($this->config, [$exchange, 'queue-array', $queue, 'queue-bind-config'], []);
        $config = array_replace(ConfigManager::$defaultQueueBindArgs, $config);
        $config['exchange'] = $exchange;
        $config['queue'] = $queue;
        $config['routing_key'] = $routingKey;

        return array_values($config);
    }

    /**
     * @param string $exchange
     * @param string $queue
     * @param callable|null $callback
     * @return array
     */
    public function getBasicConsumeArgs(string $exchange, string $queue, callable $callback = null): array
    {
        $config = ArrayHelper::getValue($this->config, [$exchange, 'queue-array', $queue, 'basic-consumer-config'], []);
        $config = array_replace(ConfigManager::$defaultBasicConsumeArgs, $config);
        $config['queue'] = $queue;

        if ($callback !== null) {
            $config['callback'] = $callback;
        }

        return array_values($config);
    }

    /**
     * @param string $exchange
     * @param string $queue
     * @return array
     */
    public function getBasicQosArgs(string $exchange, string $queue): array
    {
        $config = ArrayHelper::getValue($this->config, [$exchange, 'queue-array', $queue, 'basic-qos-config'], []);
        $config = array_replace(ConfigManager::$defaultBasicConsumeArgs, $config);
        $config['queue'] = $queue;

        return array_values($config);
    }

    /**
     * @param $message
     * @param string $exchange
     * @param string $routingKey
     * @param array $publishArgs
     * @return array
     */
    public function getPublishArgs($message, string $exchange, string $routingKey, array $publishArgs = []): array
    {
        return array_values(array_replace(ConfigManager::$defaultPublishArgs, $publishArgs, [
            'msg' => $message,
            'exchange' => $exchange,
            'routing_key' => $routingKey,
        ]));
    }
}
