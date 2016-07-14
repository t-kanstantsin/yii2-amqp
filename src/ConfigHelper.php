<?php
/**
 * @link https://github.com/t-kanstantsin/yii2-amqp
 * @copyright Copyright (c) 2016 t-kanstantsin
 * @license https://github.com/t-kanstantsin/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp;

use yii\helpers\ArrayHelper;

/**
 * Class ConfigManager contains default function args values
 *
 * @author Kanstantsin Tsimashenka <t.kanstantsin@gmail.com>
 */
class ConfigHelper
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

    /**
     * @param $config
     * @param string $exchange
     * @return array
     */
    public static function getExchangeDeclareArgs($config, string $exchange): array
    {
        $config = ArrayHelper::getValue($config, [$exchange, 'exchange-config'], []);
        $config = array_replace(ConfigHelper::$defaultExchangeArgs, $config);
        $config['exchange'] = $exchange;

        return array_values($config);
    }

    /**
     * @param $config
     * @param string $exchange
     * @param string $queue
     * @return array
     */
    public static function getQueueDeclareArgs($config, string $exchange, string $queue): array
    {
        // TODO: take in account that routing key might be different from queue name.
        $config = ArrayHelper::getValue($config, [$exchange, 'queue-array', $queue, 'queue-config'], []);
        $config = array_replace(ConfigHelper::$defaultQueueArgs, $config);
        $config['queue'] = $queue;

        return array_values($config);
    }

    /**
     * @param $config
     * @param string $exchange
     * @param string $queue
     * @param string $routingKey
     * @return array
     */
    public static function getQueueBindArgs($config, string $exchange, string $queue, string $routingKey): array
    {
        // TODO: take in account that routing key might be different from queue name.
        $config = ArrayHelper::getValue($config, [$exchange, 'queue-array', $queue, 'queue-bind-config'], []);
        $config = array_replace(ConfigHelper::$defaultQueueBindArgs, $config);
        $config['exchange'] = $exchange;
        $config['queue'] = $queue;
        $config['routing_key'] = $routingKey;

        return array_values($config);
    }

    /**
     * @param $config
     * @param string $exchange
     * @param string $queue
     * @param callable|null $callback
     * @return array
     */
    public static function getBasicConsumeArgs($config, string $exchange, string $queue, callable $callback = null): array
    {
        $config = ArrayHelper::getValue($config, [$exchange, 'queue-array', $queue, 'basic-consumer-config'], []);
        $config = array_replace(ConfigHelper::$defaultBasicConsumeArgs, $config);
        $config['queue'] = $queue;

        if ($callback !== null) {
            $config['callback'] = $callback;
        }

        return array_values($config);
    }

    /**
     * @param $config
     * @param string $exchange
     * @param string $queue
     * @return array
     */
    public static function getBasicQosArgs($config, string $exchange, string $queue): array
    {
        $config = ArrayHelper::getValue($config, [$exchange, 'queue-array', $queue, 'basic-qos-config'], []);
        $config = array_replace(ConfigHelper::$defaultBasicConsumeArgs, $config);
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
    public static function getPublishArgs($message, string $exchange, string $routingKey, array $publishArgs = []): array
    {
        return array_values(array_replace(ConfigHelper::$defaultPublishArgs, $publishArgs, [
            'msg' => $message,
            'exchange' => $exchange,
            'routing_key' => $routingKey,
        ]));
    }

}