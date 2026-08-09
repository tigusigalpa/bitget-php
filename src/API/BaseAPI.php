<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\API;

use Tigusigalpa\Bitget\Client;

abstract class BaseAPI
{
    public function __construct(protected Client $client)
    {
    }
}
