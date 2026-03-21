<?php

namespace Romansh\LaravelCreemAgent\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy transactions()
 * @method static \Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy subscriptions()
 * @method static \Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy customers()
 * @method static \Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy products()
 * @method static bool isNativeCliAvailable()
 * @method static array execute(string $command, array $args = [], ?string $store = null)
 *
 * @see \Romansh\LaravelCreemAgent\Cli\CreemCliManager
 */
class CreemCli extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'creem-cli';
    }
}
