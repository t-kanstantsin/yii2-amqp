<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace webtoucher\amqp\controllers;

use PhpAmqpLib\Message\AMQPMessage;
use webtoucher\amqp\components\AmpqInterpreterInterface;
use webtoucher\amqp\components\AmqpInterpreter;
use yii\console\Exception;
use yii\helpers\Inflector;
use yii\helpers\Json;


/**
 * AMQP listener controller.
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @since 2.0
 */
class AmqpListenerController extends AmqpConsoleController
{
    /**
     * Interpreter classes for AMQP messages. This class will be used if
     * interpreter class not set for exchange.
     *
     * @var array
     */
    public $interpreters = [];

    public function actionRun()
    {
        $this->amqp->listenQueue($this->queue, [$this, 'callback'], $this->break);
    }

    public function callback(AMQPMessage $msg)
    {
        $routingKey = $msg->get('routing_key');
        $method = 'read' . Inflector::camelize($routingKey);

        $interpreter = isset($this->interpreters[$this->exchange])
            ? $this->interpreters[$this->exchange]
            : (isset($this->interpreters['*']) ? $this->interpreters['*'] : null);

        if ($interpreter === null) {
            $interpreter = $this;
        } else {
            if (class_exists($interpreter)) {
                $interpreter = new $interpreter;
                if (!$interpreter instanceof AmqpInterpreter) {
                    throw new Exception(sprintf("Class '%s' is not correct interpreter class.", $interpreter));
                }
            } else {
                throw new Exception(sprintf("Interpreter class '%s' was not found.", $interpreter));
            }
        }

        if (method_exists($interpreter, $method) || is_callable([$interpreter, $method])) {
            $info = [
                'exchange' => $this->exchange,
                'routing_key' => $routingKey,
                'reply_to' => $msg->has('reply_to') ? $msg->get('reply_to') : null,
                'delivery_tag' => $msg->get('delivery_tag'),
            ];
            try {
                $body = Json::decode($msg->body, true);
            } catch (\Exception $e) {
                $body = $msg->body;
            }
            $interpreter->$method($body, $info, $this->amqp->channel);
        } else {
            if (!$interpreter instanceof AmqpInterpreter) {
                $interpreter = new AmqpInterpreter();
            }
            $interpreter->log(
                sprintf("Unknown routing key '%s' for exchange '%s'.", $routingKey, $this->exchange),
                $interpreter::MESSAGE_ERROR
            );
            // debug the message
            $interpreter->log(
                print_r(Json::decode($msg->body, true), true),
                $interpreter::MESSAGE_INFO
            );
        }
    }
}
