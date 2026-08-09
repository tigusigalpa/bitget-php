<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\Facades;

use Illuminate\Support\Facades\Facade;
use Tigusigalpa\Bitget\Client;

/**
 * @method static \Tigusigalpa\Bitget\API\Market market()
 * @method static \Tigusigalpa\Bitget\API\Account account()
 * @method static \Tigusigalpa\Bitget\API\Trade trade()
 *
 * @see \Tigusigalpa\Bitget\Client
 */
class Bitget extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
