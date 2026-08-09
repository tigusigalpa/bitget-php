<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\Exceptions;

/**
 * Thrown when the account lacks sufficient balance or margin to complete a
 * trade or transfer request.
 */
class InsufficientFundsException extends BitgetException
{
}
