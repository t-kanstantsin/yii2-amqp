<?php
/**
 * @link https://github.com/t-kanstantsin/yii2-amqp
 * @copyright Copyright (c) 2016 t-kanstantsin
 * @license https://github.com/t-kanstantsin/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\amqp\components;

/**
 * Class ConfigManager contains default function args values
 */
class ConfigManager
{
    public static $defaultExchangeArgs = [
        'exchange' => '',
        'type' => Amqp::TYPE_FANOUT,
        'passive' => false,
        'durable' => false,
        'auto_delete' => true,
        'internal' => false,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null,
    ];
    public static $defaultQueueArgs = [
        'queue' => '',
        'passive' => false,
        'durable' => false,
        'exclusive' => false,
        'auto_delete' => true,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null,
    ];
    public static $defaultQueueBindArgs = [
        'queue' => '',
        'exchange' => '',
        'routing_key' => '',
        'nowait' => false,
        'arguments' => null,
        'ticket' => null,
    ];
    public static $defaultBasicConsumeArgs = [
        'queue' => '',
        'consumer_tag' => '',
        'no_local' => false,
        'no_ack' => false,
        'exclusive' => false,
        'nowait' => false,
        'callback' => null,
        'ticket' => null,
        'arguments' => [],
    ];
    public static $defaultBasicQos = [
        'prefetch_size' => 0,
        'prefetch_count' => 0,
        'a_global' => false,
    ];

    public static $defaultPublishArgs = [
        'msg' => null,
        'exchange' => '',
        'routing_key' => '',
        'mandatory' => false,
        'immediate' => false,
        'ticket' => null,
    ];

}