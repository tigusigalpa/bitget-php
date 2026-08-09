<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\Exceptions;

/**
 * Base exception for every error returned by the Bitget UTA v3 API,
 * carrying the exchange's raw error code and message.
 *
 * Docs: https://www.bitget.com/api-doc/uta/guide
 */
class BitgetException extends \RuntimeException
{
    public function __construct(
        public readonly string $bitgetCode,
        string $bitgetMessage,
        public readonly string $rawResponse = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Bitget API error: code=%s, message=%s', $bitgetCode, $bitgetMessage),
            0,
            $previous,
        );
    }
}
