<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\amqp\components;

use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use PhpAmqpLib\Message\AMQPMessage;
use yii\base\Object;
use yii\helpers\Console;
use yii\helpers\Inflector;


/**
 * AMQP interpreter class.
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @since 2.0
 */
class AmqpInterpreter extends Object
{
    const MESSAGE_INFO = 0;
    const MESSAGE_ERROR = 1;

    /**
     * @var AMQPMessage
     */
    public $msg;

    public function __call($name, $params)
    {
        $action = $this->createAction($name);
        if ($action !== null) {
            return $action->run();
        }

        $method = 'action' . Inflector::camelize($name);
        if (!method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $params);
        }

        return parent::__call($name, $params);
    }

    /**
     * @return array
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function hasMethod($name)
    {
        return parent::hasMethod($name) || $this->hasAction($name);
    }


    /**
     * Creates an action based on the given action ID.
     * @param string $id the action ID.
     * @return InterpreterAction
     * @throws \yii\base\InvalidParamException
     */
    public function createAction($id)
    {
        $action = ArrayHelper::getValue($this->actions(), $id, null);
        if ($action === null) {
            return null;
        }

        return new $action($this);
    }

    /**
     * @param string $action
     * @return bool
     */
    public function hasAction(string $action): bool
    {
        return array_key_exists($action, $this->actions());
    }

    /**
     * @return mixed|string
     */
    public function getBody()
    {
        try {
            $body = Json::decode($this->msg->body, true);
        } catch (\Exception $e) {
            $body = $this->msg->body;
        }

        return $body;
    }

    /**
     * Logs info and error messages.
     *
     * @param $message
     * @param int $type
     */
    public function log($message, int $type = self::MESSAGE_INFO)
    {
        $format = [$type === self::MESSAGE_ERROR ? Console::FG_RED : Console::FG_BLUE];
        Console::stdout(Console::ansiFormat($message . PHP_EOL, $format));
    }

    /**
     * Message acknowledgment
     */
    public function ack()
    {
        $this->msg
            ->delivery_info['channel']
            ->basic_ack($this->msg->delivery_info['delivery_tag']);
    }

    /**
     * Message rejecting
     * @param bool $requeue
     */
    public function reject(bool $requeue = true)
    {
        $this->msg
            ->delivery_info['channel']
            ->basic_reject($this->msg->delivery_info['delivery_tag'], $requeue);
    }

    /**
     * @return mixed|\PhpAmqpLib\Channel\AMQPChannel|null
     * @throws \OutOfBoundsException
     */
    public function getReplyTo()
    {
        return $this->msg->has('reply_to')
            ? $this->msg->get('reply_to')
            : null;
    }
}