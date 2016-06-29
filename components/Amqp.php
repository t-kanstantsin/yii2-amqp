<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace webtoucher\amqp\components;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\Json;


/**
 * AMQP wrapper.
 *
 * @property AMQPConnection $connection AMQP connection.
 * @property AMQPChannel $channel AMQP channel.
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @since 2.0
 */
class Amqp extends Component
{
    const TYPE_TOPIC = 'topic';
    const TYPE_DIRECT = 'direct';
    const TYPE_HEADERS = 'headers';
    const TYPE_FANOUT = 'fanout';

    /**
     * @var AMQPConnection
     */
    protected static $ampqConnection;

    /**
     * @var AMQPChannel[]
     */
    protected $channels = [];

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
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (empty($this->user)) {
            throw new Exception("Parameter 'user' was not set for AMQP connection.");
        }
        if (empty(self::$ampqConnection)) {
            self::$ampqConnection = new AMQPConnection(
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
     * @return AMQPConnection
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
     * @param string $routing_key
     * @param string|array $message
     * @param null $headers
     * @param string $type Use self::TYPE_DIRECT if it is an answer
     */
    public function send($exchange, $routing_key, $message, $headers = null, $type = self::TYPE_TOPIC)
    {
        $properties = [];
        $this->applyPropertyHeaders($properties, $headers);

        if ($type === self::TYPE_TOPIC) {
            $this->channel->exchange_declare($exchange, $type, false, true, false);
        }

        $message = $this->prepareMessage($message, $properties);
        $this->channel->basic_publish($message, $exchange, $routing_key);
    }

    /**
     * Sends message to the exchange.
     *
     * @param string $exchange
     * @param string $routing_key
     * @param array $messageArray
     * @param null $headers
     * @param string $type Use self::TYPE_DIRECT if it is an answer
     * @internal param array|string $message
     * @throws \yii\base\Exception
     */
    public function batchSend($exchange, $routing_key, array $messageArray, $headers = null, $type = self::TYPE_TOPIC)
    {
        $properties = [];
        $this->applyPropertyHeaders($properties, $headers);

        if ($type === self::TYPE_TOPIC) {
            $this->channel->exchange_declare($exchange, $type, false, true, false);
        }

        foreach ($messageArray as $message) {
            $message = $this->prepareMessage($message, $properties);
            $this->channel->batch_basic_publish($message, $exchange, $routing_key);
        }
        $this->channel->publish_batch();
    }

    /**
     * Sends message to the exchange and waits for answer.
     *
     * @param string $exchange
     * @param string $routing_key
     * @param string|array $message
     * @param integer $timeout Timeout in seconds.
     * @return string
     */
    public function ask($exchange, $routing_key, $message, $timeout)
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
        $this->channel->basic_publish($message, $exchange, $routing_key);
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
     * @param string $routing_key
     * @param callable $callback
     * @param string $type
     */
    public function listen($exchange, $routing_key, $callback, $type = self::TYPE_TOPIC, $queue = '')
    {
        $queueName = $queue;
        if (!$queueName) {
            list ($queueName) = $this->channel->queue_declare();
            if ($type === self::TYPE_DIRECT) {
                $this->channel->exchange_declare($exchange, $type, false, true, false);
            }
        }
        $this->channel->queue_bind($queueName, $exchange, $routing_key);
        $this->channel->basic_consume($queueName, '', false, $this->consumeNoAck, false, false, $callback);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }

        $this->channel->close();
        $this->connection->close();
    }

    /**
     * Listens the queue for messages.
     *
     * @param string $queueName
     * @param callable $callback
     */
    public function listenQueue($queueName, $callback, $break = false)
    {
        while (true) {
            if (($message = $this->channel->basic_get($queueName, $this->consumeNoAck)) instanceof AMQPMessage) {
                call_user_func($callback, $message);
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
     * Returns prepaired AMQP message.
     *
     * @param string|array|object $message
     * @param array $properties
     * @return AMQPMessage
     * @throws Exception If message is empty.
     */
    public function prepareMessage($message, $properties = null)
    {
        if (empty($message)) {
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
}
