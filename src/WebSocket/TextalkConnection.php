<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\WebSocket;

use WebSocket\Client as PhrityClient;
use WebSocket\Configuration;
use WebSocket\Exception\ConnectionTimeoutException;
use WebSocket\Exception\ExceptionInterface;

/**
 * Default, blocking/synchronous ConnectionInterface implementation built on
 * phrity/websocket. Suitable for CLI workers and queue jobs; for a
 * non-blocking event loop (Laravel Octane, ReactPHP, Amp), implement
 * ConnectionInterface yourself and pass it to WebsocketClient's
 * constructor instead.
 */
final class TextalkConnection implements ConnectionInterface
{
    private ?PhrityClient $client = null;

    public function __construct(private readonly int $timeoutSeconds = 5)
    {
    }

    public function connect(string $url): void
    {
        try {
            $this->client = new PhrityClient($url, new Configuration(timeout: $this->timeoutSeconds));
            $this->client->connect();
        } catch (\Throwable $e) {
            $this->client = null;
            throw new ConnectionClosedException($e->getMessage(), 0, $e);
        }
    }

    public function send(string $payload): void
    {
        if ($this->client === null) {
            throw new ConnectionClosedException('Not connected.');
        }

        try {
            $this->client->text($payload);
        } catch (ExceptionInterface $e) {
            throw new ConnectionClosedException($e->getMessage(), 0, $e);
        }
    }

    public function receive(): ?string
    {
        if ($this->client === null) {
            throw new ConnectionClosedException('Not connected.');
        }

        try {
            return $this->client->receive()->getContent();
        } catch (ConnectionTimeoutException) {
            return null;
        } catch (ExceptionInterface $e) {
            throw new ConnectionClosedException($e->getMessage(), 0, $e);
        }
    }

    public function close(): void
    {
        try {
            $this->client?->close();
        } finally {
            $this->client = null;
        }
    }
}
