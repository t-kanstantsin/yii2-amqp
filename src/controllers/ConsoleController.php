<?php
/**
 * @link https://github.com/webtoucher/yii2-amqp
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp\controllers;

use tkanstantsin\yii2\amqp\AmqpTrait;
use yii\console\Controller;


/**
 * AMQP console controller.
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @since 2.0
 */
abstract class ConsoleController extends Controller
{
    use AmqpTrait;

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
}
