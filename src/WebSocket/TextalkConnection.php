<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\WebSocket;

use WebSocket\Client as TextalkClient;
use WebSocket\ConnectionException;
use WebSocket\TimeoutException;

/**
 * Default, blocking/synchronous ConnectionInterface implementation built on
 * textalk/websocket. Suitable for CLI workers and queue jobs; for a
 * non-blocking event loop (Laravel Octane, ReactPHP, Amp), implement
 * ConnectionInterface yourself and pass it to WebsocketClient's
 * constructor instead.
 */
final class TextalkConnection implements ConnectionInterface
{
    private ?TextalkClient $client = null;

    public function __construct(private readonly int $timeoutSeconds = 30)
    {
    }

    public function connect(string $url): void
    {
        $this->client = new TextalkClient($url, ['timeout' => $this->timeoutSeconds]);
    }

    public function send(string $payload): void
    {
        $this->client?->send($payload);
    }

    public function receive(): ?string
    {
        if ($this->client === null) {
            throw new ConnectionClosedException('Not connected.');
        }

        try {
            $message = $this->client->receive();
        } catch (TimeoutException) {
            return null;
        } catch (ConnectionException $e) {
            throw new ConnectionClosedException($e->getMessage(), 0, $e);
        }

        return $message === false ? null : $message;
    }

    public function close(): void
    {
        $this->client?->close();
        $this->client = null;
    }
}
