<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\WebSocket;

/**
 * Thrown by a ConnectionInterface implementation when the underlying
 * socket has been closed unexpectedly, signalling WebsocketClient to
 * reconnect.
 */
class ConnectionClosedException extends \RuntimeException
{
}
