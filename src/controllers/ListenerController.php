<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp\controllers;

use PhpAmqpLib\Message\AMQPMessage;
use tkanstantsin\yii2\amqp\Amqp;
use tkanstantsin\yii2\amqp\interpreter\Interpreter;
use yii\base\InvalidConfigException;
use yii\console\Controller;


/**
 * AMQP listener controller.
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @author Kanstantsin Tsimashenka <t.kanstantsin@gmail.com>
 * @since 2.0
 */
class ListenerController extends Controller
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
     * Amqp component
     * @var Amqp|string
     */
    public $amqp = 'amqp';

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (is_string($this->amqp)) {
            $this->amqp = \Yii::$app->get($this->amqp);
        }
        if (!($this->amqp instanceof Amqp)) {
            throw new InvalidConfigException(sprintf('Amqp component MUST be defined and be instance of "%s".', Amqp::class));
        }
    }

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        return array_merge(
            parent::options($actionId),
            ['exchange', 'queue', 'break']
        );
    }

    public function actionRun()
    {
        $this->amqp->listen($this->exchange, $this->queue, [$this, 'callback']);
    }

    /**
     * @param AMQPMessage $msg
     * @throws \OutOfBoundsException
     * @throws \yii\base\InvalidParamException
     * @throws \yii\console\Exception
     */
    public function callback(AMQPMessage $msg)
    {
        $interpreter = $this->amqp->buildInterpreter($this->exchange, $msg);
        $action = $msg->get('routing_key');

        if ($interpreter->hasMethod($action)) {
            $interpreter->$action();
        } else {
            $this->logError(sprintf("Unknown routing key '%s' for exchange '%s'.", $action, $this->exchange), $action, $msg);
        }
    }

    /**
     * @param $logMessage
     * @param $routingKey // TODO: check and: use or remove.
     * @param AMQPMessage $msg
     * @param Interpreter|null $interpreter
     */
    private function logError($logMessage, $routingKey, AMQPMessage $msg)
    {
        // error
        $this->amqp->log($logMessage, Amqp::MESSAGE_ERROR);
        // debug the message
        $this->amqp->log(print_r($msg->body, true), Amqp::MESSAGE_INFO);
    }
}
