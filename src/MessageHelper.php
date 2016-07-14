<?php
/**
 * @link https://github.com/t-kanstantsin/yii2-amqp
 * @copyright Copyright (c) 2016 t-kanstantsin
 * @license https://github.com/t-kanstantsin/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp;

use PhpAmqpLib\Message\AMQPMessage;
use yii\helpers\Json;

/**
 * Class MessageHelper
 *
 * @author Kanstantsin Tsimashenka <t.kanstantsin@gmail.com>
 */
class MessageHelper
{

    /**
     * Returns prepared AMQP message.
     *
     * @param string|array|object $message
     * @param array $properties
     * @return AMQPMessage
     * @throws \ErrorException
     */
    public static function prepareMessage($message, $properties = null): AMQPMessage
    {
        if ($message === null || $message === '') {
            throw new \ErrorException('AMQP message can not be empty');
        }

        if (is_array($message) || is_object($message)) {
            $message = Json::encode($message);
        }

        return new AMQPMessage($message, $properties);
    }

}