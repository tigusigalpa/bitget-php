<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tigusigalpa\Bitget\WebSocket\ConnectionClosedException;
use Tigusigalpa\Bitget\WebSocket\ConnectionInterface;
use Tigusigalpa\Bitget\WebSocket\TextalkConnection;

/**
 * Reconnecting WebSocket client for Bitget's public or private UTA v3
 * streams. The transport is pluggable via ConnectionInterface (defaults to
 * a synchronous textalk/websocket-backed implementation).
 *
 * Docs: https://www.bitget.com/api-doc/uta/websocket/private/Fast-Fill-Channel
 */
class WebsocketClient
{
    public const DEFAULT_PUBLIC_URL = 'wss://ws.bitget.com/v3/ws/public';
    public const DEFAULT_PRIVATE_URL = 'wss://ws.bitget.com/v3/ws/private';
    public const DEMO_PUBLIC_URL = 'wss://wspap.bitget.com/v3/ws/public';
    public const DEMO_PRIVATE_URL = 'wss://wspap.bitget.com/v3/ws/private';

    private const PING_INTERVAL_SECONDS = 25;
    private const PONG_TIMEOUT_SECONDS = 30;
    private const RECONNECT_MIN_SECONDS = 1;
    private const RECONNECT_MAX_SECONDS = 60;

    private ConnectionInterface $connection;
    private LoggerInterface $logger;
    private Signer $signer;

    /** @var array<string, array> subscription key => args, restored after reconnect */
    private array $subscriptions = [];

    private bool $shouldRun = false;

    public function __construct(
        private readonly string $url,
        private readonly ?string $apiKey = null,
        private readonly ?string $secretKey = null,
        private readonly ?string $passphrase = null,
        ?ConnectionInterface $connection = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->connection = $connection ?? new TextalkConnection();
        $this->logger = $logger ?? new NullLogger();
        $this->signer = new Signer($this->secretKey ?? '');
    }

    private function isPrivate(): bool
    {
        return $this->apiKey !== null && $this->secretKey !== null && $this->passphrase !== null;
    }

    /**
     * Connects and, for a private client, authenticates. Idempotent.
     */
    public function connect(): void
    {
        $this->connection->connect($this->url);
        $this->logger->info('Bitget websocket connected', ['url' => $this->url]);

        if ($this->isPrivate()) {
            $this->login();
        }
    }

    private function login(): void
    {
        $timestamp = $this->signer->generateTimestamp();
        $this->connection->send(json_encode([
            'op' => 'login',
            'args' => [[
                'apiKey' => $this->apiKey,
                'passphrase' => $this->passphrase,
                'timestamp' => $timestamp,
                'sign' => $this->signer->signWsLogin($timestamp),
            ]],
        ], JSON_THROW_ON_ERROR));

        // Bitget acknowledges login with {"event":"login",...} before any
        // channel data is pushed; block briefly for it here so callers can
        // treat connect() as "ready to subscribe" without listen()ing first.
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $raw = $this->connection->receive();
            if ($raw === null) {
                continue;
            }
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning('Bitget websocket login response decode failed', ['error' => $e->getMessage()]);
                continue;
            }
            if (! is_array($decoded)) {
                continue;
            }
            if (($decoded['event'] ?? null) === 'error') {
                throw new \RuntimeException(sprintf(
                    'Bitget websocket login failed: code=%s, message=%s',
                    (string) ($decoded['code'] ?? 'UNKNOWN'),
                    (string) ($decoded['msg'] ?? 'Unknown error'),
                ));
            }
            if (($decoded['event'] ?? null) === 'login') {
                $this->logger->info('Bitget websocket login succeeded');

                return;
            }
        }

        throw new \RuntimeException('Bitget websocket login timed out');
    }

    /**
     * Subscribes to a channel. $arg keys: instType, topic, and optionally
     * symbol/coin, per the channel's documentation. Restored automatically
     * after a reconnect.
     */
    public function subscribe(array $arg): void
    {
        $key = $this->subscriptionKey($arg);
        $this->subscriptions[$key] = $arg;
        $this->connection->send(json_encode(['op' => 'subscribe', 'args' => [$arg]], JSON_THROW_ON_ERROR));
    }

    public function unsubscribe(array $arg): void
    {
        $key = $this->subscriptionKey($arg);
        unset($this->subscriptions[$key]);
        $this->connection->send(json_encode(['op' => 'unsubscribe', 'args' => [$arg]], JSON_THROW_ON_ERROR));
    }

    private function subscriptionKey(array $arg): string
    {
        return implode(':', [
            $arg['instType'] ?? '',
            $arg['topic'] ?? '',
            $arg['symbol'] ?? '',
            $arg['coin'] ?? '',
        ]);
    }

    /**
     * Blocks, dispatching every decoded data push to $onMessage(array
     * $push), until stop() is called or an unrecoverable error occurs.
     * Automatically reconnects (exponential backoff, 1s-60s) and
     * resubscribes on unexpected disconnects, and answers Bitget's
     * text-frame ping/pong heartbeat.
     *
     * @param callable(array): void $onMessage
     */
    public function listen(callable $onMessage): void
    {
        $this->shouldRun = true;
        $lastPing = microtime(true);
        $lastPong = $lastPing;

        while ($this->shouldRun) {
            try {
                $raw = $this->connection->receive();
                $now = microtime(true);

                if ($now - $lastPing >= self::PING_INTERVAL_SECONDS) {
                    $this->connection->send('ping');
                    $lastPing = $now;
                }
                if ($now - $lastPong >= self::PONG_TIMEOUT_SECONDS) {
                    throw new ConnectionClosedException('Bitget websocket pong timed out.');
                }

                if ($raw === null) {
                    continue;
                }
                if ($raw === 'pong') {
                    $lastPong = $now;
                    continue;
                }

                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $this->logger->warning('Bitget websocket message decode failed', ['error' => $e->getMessage()]);
                    continue;
                }
                if (! is_array($decoded)) {
                    continue;
                }

                if (isset($decoded['event'])) {
                    if ($decoded['event'] === 'error') {
                        $this->logger->error('Bitget websocket error event', $decoded);
                    }
                    continue;
                }

                $onMessage($decoded);
            } catch (ConnectionClosedException $e) {
                $this->logger->warning('Bitget websocket disconnected, reconnecting', ['error' => $e->getMessage()]);
                $this->reconnectWithBackoff();
                $lastPing = microtime(true);
                $lastPong = $lastPing;
            }
        }
    }

    private function reconnectWithBackoff(): void
    {
        $backoff = self::RECONNECT_MIN_SECONDS;

        while ($this->shouldRun) {
            sleep($backoff);

            try {
                $this->connect();
                foreach ($this->subscriptions as $arg) {
                    $this->connection->send(json_encode(['op' => 'subscribe', 'args' => [$arg]], JSON_THROW_ON_ERROR));
                }
                $this->logger->info('Bitget websocket reconnected');

                return;
            } catch (\Throwable $e) {
                $this->logger->warning('Bitget websocket reconnect failed', ['error' => $e->getMessage(), 'backoff' => $backoff]);
                $backoff = min($backoff * 2, self::RECONNECT_MAX_SECONDS);
            }
        }
    }

    /**
     * Stops the listen() loop after the current message and closes the
     * connection.
     */
    public function stop(): void
    {
        $this->shouldRun = false;
        $this->connection->close();
    }
}
