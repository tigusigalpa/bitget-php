<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\Exceptions;

/**
 * Thrown when the API key, signature, passphrase, or timestamp is invalid,
 * missing, or expired.
 */
class AuthenticationException extends BitgetException
{
}
