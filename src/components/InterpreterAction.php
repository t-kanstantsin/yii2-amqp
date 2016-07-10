<?php
/**
 * @link https://github.com/t-kanstantsin/yii2-amqp
 * @copyright Copyright (c) 2016 t-kanstantsin
 * @license https://github.com/t-kanstantsin/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\amqp\components;

use yii\base\Object;
use yii\helpers\ArrayHelper;

/**
 * Class InterpreterAction runs queue's business logic
 */
abstract class InterpreterAction extends Object
{
    /**
     * @var AmqpInterpreter
     */
    public $interpreter;

    /**
     * InterpreterAction constructor.
     * @param AmqpInterpreter $interpreter
     * @param array $config
     */
    public function __construct(AmqpInterpreter $interpreter, $config = [])
    {
        $this->interpreter = $interpreter;

        parent::__construct($config);
    }

    /**
     * Returns parameter from message by parameter name
     * @param mixed $parameterName
     * @param mixed|null $default
     * @return mixed
     */
    protected function getMsgParameter($parameterName, $default = null)
    {
        return ArrayHelper::getValue($this->interpreter->getBody(), $parameterName, $default);
    }

    /**
     * Executes queue task
     */
    abstract public function run();
}