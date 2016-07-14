<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Yii;


/**
 * AMQP trait for controllers.
 *
 * @property Amqp $amqp AMQP object.
 * @property AMQPStreamConnection $connection AMQP connection.
 * @property AMQPChannel $channel AMQP channel.
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @since 2.0
 */
trait AmqpTrait
{
    /**
     * Listened exchange.
     *
     * @var string
     */
    public $exchange = 'exchange';
    /**
     * bind queue.
     *
     * @var string
     */
    public $queue = '';
    /**
     * break listen
     *
     * @var boolean
     */
    public $break = false;
    /**
     * @var Amqp
     */
    protected $amqpContainer;

    /**
     * Returns AMQP object.
     *
     * @return Amqp
     */
    public function getAmqp()
    {
        if ($this->amqpContainer === null) {
            $this->amqpContainer = Yii::$app->amqp;
        }

        return $this->amqpContainer;
    }

    /**
     * Returns AMQP connection.
     *
     * @return AMQPStreamConnection
     */
    public function getConnection()
    {
        return $this->amqp->getConnection();
    }

    /**
     * Returns AMQP channel.
     *
     * @param string $channel_id
     * @return AMQPChannel
     */
    public function getChannel($channel_id = null)
    {
        return $this->amqp->getChannel($channel_id);
    }
}
