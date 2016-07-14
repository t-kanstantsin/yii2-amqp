yii2-amqp
=========

AMQP extension wrapper to communicate with RabbitMQ server. Based on [videlalvaro/php-amqplib](https://github.com/videlalvaro/php-amqplib).

This extension is reworked version of [webtoucher\amqp](https://github.com/webtoucher/yii2-amqp) extension

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
$ php composer.phar require t-kanstantsin/yii2-amqp "*"
```

or add

```
"t-kanstantsin/yii2-amqp": "*"
```

to the ```require``` section of your `composer.json` file.

Add the following in your console config:

```php
<?php
return [
    'components' => [
        'amqp' => [
            'class' => tkanstantsin\yii2\amqp\Amqp::class,
            'host' => '127.0.0.1',
            'port' => 5672,
            'user' => 'your_login',
            'password' => 'your_password',
            'vhost' => '/',
        ],
    ],
    'controllerMap' => [
        'rabbit' => [
            'class' => tkanstantsin\yii2\amqp\controllers\ListenerController::class,
            'interpreters' => [
                'my-exchange' => app\components\RabbitInterpreter::class, // interpreters for each exchange
            ],
            'exchange' => 'my-exchange', // default exchange
        ],
    ],
];
```

Add messages interpreter class `@app/components/RabbitInterpreter` with your handlers for different routing keys:

```php
<?php
namespace app\components;

use tkanstantsin\yii2\amqp\interpreter\Interpreter;

class RabbitInterpreter extends Interpreter
{
    /**
     * Interprets AMQP message with routing key 'hello_world'.
     *
     * @param array $message
     */
    public function readHelloWorld($message)
    {
        // todo: write message handler
        $this->log(print_r($message, true));
    }
}
```

## Usage

Just run command

```bash
$ php yii rabbit/run
```

to listen topics with any routing keys on default exchange or

```bash
$ php yii rabbit/run my_routing_key
```

to listen topics with one routing key.

Run command

```bash
$ php yii rabbit/run my_routing_key direct --exchange=my_exchange
```

to listen direct messages on selected exchange.

Run command
```bash
$ php yii rabbit/run --exchange=my_exchange --queue=queue1
```

to listen messages on selected exchange binding queue1 

Also you can create controllers for your needs. Just use for your web controllers class
`tkanstantsin\yii2\amqp\controllers\AmqpConsoleController` instead of `yii\web\Controller` and for your console controllers
class `tkanstantsin\yii2\amqp\controllers\AmqpConsoleController` instead of `yii\console\Controller`. AMQP connection will be
available with property `connection`. AMQP channel will be available with property `channel`.
