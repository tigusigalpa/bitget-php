<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\WebSocket;

/**
 * Minimal WebSocket transport contract used by WebsocketClient. The default
 * binding (TextalkConnection) is a blocking, synchronous implementation
 * built on textalk/websocket. Implement this interface to plug in a
 * non-blocking event-loop transport (e.g. ReactPHP's react/socket +
 * react/http, or Amp's amphp/websocket-client) without changing any
 * subscription/reconnect/auth logic in WebsocketClient.
 */
interface ConnectionInterface
{
    public function connect(string $url): void;

    public function send(string $payload): void;

    /**
     * Blocks until a message is received. Return null on a heartbeat/idle
     * timeout so the caller can send a ping and retry; throw
     * ConnectionClosedException when the underlying socket has died so the
     * caller can reconnect.
     */
    public function receive(): ?string;

    public function close(): void;
}
