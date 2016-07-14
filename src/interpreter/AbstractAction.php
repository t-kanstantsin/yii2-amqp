<?php
/**
 * @link https://github.com/t-kanstantsin/yii2-amqp
 * @copyright Copyright (c) 2016 t-kanstantsin
 * @license https://github.com/t-kanstantsin/yii2-amqp/blob/master/LICENSE.md
 */

namespace tkanstantsin\yii2\amqp\interpreter;

use yii\base\Object;
use yii\helpers\ArrayHelper;

/**
 * Class InterpreterAction runs queue's business logic
 *
 * @property Interpreter $interpreter
 *
 * @author Kanstantsin Tsimashenka <t.kanstantsin@gmail.com>
 */
abstract class AbstractAction extends Object
{
    /**
     * @var Interpreter
     */
    private $_interpreter;

    /**
     * InterpreterAction constructor.
     * @param Interpreter $interpreter
     * @param array $config
     */
    public function __construct(Interpreter $interpreter, $config = [])
    {
        $this->_interpreter = $interpreter;

        parent::__construct($config);
    }

    /**
     * Executes queue task
     */
    abstract public function run();

    /**
     * @return Interpreter
     */
    public function getInterpreter(): Interpreter
    {
        return $this->_interpreter;
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
}