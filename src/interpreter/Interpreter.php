<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp\interpreter;

use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use PhpAmqpLib\Message\AMQPMessage;
use yii\base\Object;
use yii\helpers\Console;
use yii\helpers\Inflector;


/**
 * AMQP interpreter class.
 * Class represent functionality to serve an exchange with multiple queues.
 * Queues can be hold by special named method (with "action" prefix)
 * or by separated instance of [[tkanstantsin\yii2\amqp\interpreterInterpreterAction]].
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @author Kanstantsin Tsimashenka <t.kanstantsin@gmail.com>
 * @since 2.0
 */
class Interpreter extends Object
{
    const MESSAGE_INFO = 0;
    const MESSAGE_ERROR = 1;

    /**
     * @var AMQPMessage
     */
    public $msg;

    /**
     * Override add an attempt to call method or action that serve incoming request
     * @param string $name
     * @param array $params
     * @return mixed
     */
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
     * List of available actions
     * @return array
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * Whether interpreter has method or action associated with passed name
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
     * Whether interpreter has an action
     * @param string $action
     * @return bool
     */
    public function hasAction(string $action): bool
    {
        return array_key_exists($action, $this->actions());
    }

    /**
     * Tries to parse body from json. If fails returns body string.
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
     * Get reply_to from message. Null otherwise.
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