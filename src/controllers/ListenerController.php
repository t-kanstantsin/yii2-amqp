<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp\controllers;

use PhpAmqpLib\Message\AMQPMessage;
use tkanstantsin\yii2\amqp\interpreter\Interpreter;
use yii\console\Exception;
use yii\helpers\ArrayHelper;


/**
 * AMQP listener controller.
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @since 2.0
 */
class ListenerController extends ConsoleController
{
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
        $interpreter = $this->createInterpreter($msg);
        $action = $msg->get('routing_key');

        if ($interpreter->hasMethod($action)) {
            $interpreter->$action();
        } else {
            $this->logError(sprintf("Unknown routing key '%s' for exchange '%s'.", $action, $this->exchange), $action, $msg, $interpreter);
        }
    }

    /**
     * @param AMQPMessage $msg
     * @return Interpreter
     * @throws \yii\base\InvalidParamException
     * @throws Exception
     */
    protected function createInterpreter(AMQPMessage $msg): Interpreter
    {
        $exchangeConfig = $this->amqp->getExchangeConfig($this->exchange);
        $interpreter = ArrayHelper::getValue($exchangeConfig, 'interpreter', null);

        // TODO: log errors.
        if (!class_exists($interpreter)) {
            throw new Exception(sprintf("Interpreter class '%s' was not found.", $interpreter));
        }
        if (!is_subclass_of($interpreter, Interpreter::class)) {
            throw new Exception(sprintf("Class '%s' is not correct interpreter class.", $interpreter));
        }

        $interpreter = new $interpreter([
            'msg' => $msg,
        ]);

        return $interpreter;
    }

    /**
     * @param $logMessage
     * @param $routingKey // TODO: check and: use or remove.
     * @param AMQPMessage $msg
     * @param Interpreter|null $interpreter
     */
    private function logError($logMessage, $routingKey, AMQPMessage $msg, Interpreter $interpreter = null)
    {
        if (!($interpreter instanceof Interpreter)) {
            $interpreter = new Interpreter();
        }

        // error
        $interpreter->log($logMessage, $interpreter::MESSAGE_ERROR);

        // debug the message
        $interpreter->log(print_r($msg->body, true), $interpreter::MESSAGE_INFO);
    }
}
