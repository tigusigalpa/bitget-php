<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\Exceptions;

/**
 * Thrown when the API key or IP has exceeded Bitget's rate limits.
 */
class RateLimitException extends BitgetException
{
}
