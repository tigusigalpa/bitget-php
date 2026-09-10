<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\Tests\Unit;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Tigusigalpa\Bitget\Client;
use Tigusigalpa\Bitget\Exceptions\AuthenticationException;
use Tigusigalpa\Bitget\Exceptions\BitgetException;
use Tigusigalpa\Bitget\Exceptions\RateLimitException;
use Tigusigalpa\Bitget\Tests\TestCase;
use Tigusigalpa\Bitget\WebsocketClient;
use Tigusigalpa\Bitget\WebSocket\ConnectionClosedException;
use Tigusigalpa\Bitget\WebSocket\ConnectionInterface;
use Tigusigalpa\Bitget\WebSocket\TextalkConnection;

final class FakeWebsocketConnection implements ConnectionInterface
{
    public array $sent = [];
    public array $received = [];
    public int $connectCount = 0;
    public int $closeCount = 0;
    public ?\Throwable $sendException = null;
    public ?int $failOnSendAttempt = null;
    private int $sendAttempts = 0;

    public function connect(string $url): void
    {
        $this->connectCount++;
    }

    public function send(string $payload): void
    {
        $this->sendAttempts++;
        if ($this->failOnSendAttempt === $this->sendAttempts) {
            throw new ConnectionClosedException('socket closed');
        }
        if ($this->sendException !== null) {
            throw $this->sendException;
        }

        $this->sent[] = $payload;
    }

    public function receive(): ?string
    {
        $next = array_shift($this->received);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function close(): void
    {
        $this->closeCount++;
    }
}

class ClientTest extends TestCase
{
    private function clientWithMockedResponses(array $responses, array &$history = []): Client
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $httpClient = new HttpClient(['handler' => $stack]);

        return new Client(
            apiKey: 'api-key',
            secretKey: 'secret-key',
            passphrase: 'passphrase',
            demoTrading: true,
            httpClient: $httpClient,
        );
    }

    public function test_request_sets_auth_headers_and_demo_header(): void
    {
        $history = [];
        $client = $this->clientWithMockedResponses([
            new Response(200, [], json_encode(['code' => '00000', 'msg' => 'success', 'requestTime' => 1, 'data' => []])),
        ], $history);

        $client->request('GET', '/api/v3/account/assets');

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('api-key', $request->getHeaderLine('ACCESS-KEY'));
        $this->assertSame('passphrase', $request->getHeaderLine('ACCESS-PASSPHRASE'));
        $this->assertNotEmpty($request->getHeaderLine('ACCESS-SIGN'));
        $this->assertNotEmpty($request->getHeaderLine('ACCESS-TIMESTAMP'));
        $this->assertSame('1', $request->getHeaderLine('paptrading'));
    }

    public function test_request_public_does_not_set_auth_headers(): void
    {
        $history = [];
        $client = $this->clientWithMockedResponses([
            new Response(200, [], json_encode(['code' => '00000', 'msg' => 'success', 'requestTime' => 1, 'data' => []])),
        ], $history);

        $client->requestPublic('GET', '/api/v3/market/tickers', ['category' => 'SPOT']);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertFalse($request->hasHeader('ACCESS-KEY'));
        $this->assertFalse($request->hasHeader('ACCESS-SIGN'));
    }

    public function test_request_returns_decoded_data(): void
    {
        $client = $this->clientWithMockedResponses([
            new Response(200, [], json_encode(['code' => '00000', 'msg' => 'success', 'requestTime' => 1, 'data' => ['uid' => '12345']])),
        ]);

        $data = $client->request('GET', '/api/v3/account/settings');
        $this->assertSame(['uid' => '12345'], $data);
    }

    public function test_request_throws_authentication_exception_on_known_code(): void
    {
        $client = $this->clientWithMockedResponses([
            new Response(200, [], json_encode(['code' => '40001', 'msg' => 'invalid API key', 'requestTime' => 1, 'data' => null])),
        ]);

        $this->expectException(AuthenticationException::class);
        $client->request('GET', '/api/v3/account/assets');
    }

    public function test_request_throws_rate_limit_exception_on_http_429(): void
    {
        $client = $this->clientWithMockedResponses([
            new Response(429, [], json_encode(['code' => '429', 'msg' => 'too many requests'])),
        ]);

        $this->expectException(RateLimitException::class);
        $client->request('GET', '/api/v3/account/assets');
    }

    public function test_request_preserves_raw_exchange_code_and_message(): void
    {
        $client = $this->clientWithMockedResponses([
            new Response(200, [], json_encode(['code' => '40001', 'msg' => 'invalid API key', 'requestTime' => 1, 'data' => null])),
        ]);

        try {
            $client->request('GET', '/api/v3/account/assets');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame('40001', $e->bitgetCode);
            $this->assertStringContainsString('invalid API key', $e->getMessage());
        }
    }

    public function test_request_rejects_unexpected_http_status_with_success_envelope(): void
    {
        $client = $this->clientWithMockedResponses([
            new Response(500, [], json_encode(['code' => '00000', 'msg' => 'success', 'requestTime' => 1, 'data' => []])),
        ]);

        try {
            $client->requestPublic('GET', '/api/v3/market/tickers');
            $this->fail('Expected BitgetException');
        } catch (BitgetException $e) {
            $this->assertSame('500', $e->bitgetCode);
            $this->assertStringContainsString('Unexpected HTTP status 500', $e->getMessage());
        }
    }

    public function test_request_rejects_oversized_response_body(): void
    {
        $client = $this->clientWithMockedResponses([
            new Response(200, [], str_repeat('x', 10485761)),
        ]);

        try {
            $client->requestPublic('GET', '/api/v3/market/tickers');
            $this->fail('Expected BitgetException');
        } catch (BitgetException $e) {
            $this->assertSame('RESPONSE_TOO_LARGE', $e->bitgetCode);
        }
    }

    public function test_request_throws_domain_exception_when_body_cannot_be_encoded(): void
    {
        $client = $this->clientWithMockedResponses([]);

        try {
            $client->request('POST', '/api/v3/trade/place-order', [], ['symbol' => "\xB1"]);
            $this->fail('Expected BitgetException');
        } catch (BitgetException $e) {
            $this->assertSame('ENCODE_ERROR', $e->bitgetCode);
        }
    }

    public function test_default_websocket_transport_uses_maintained_connection_adapter(): void
    {
        $this->assertInstanceOf(ConnectionInterface::class, new TextalkConnection());
    }

    public function test_websocket_login_returns_exchange_error_immediately(): void
    {
        $connection = new FakeWebsocketConnection();
        $connection->received[] = json_encode(['event' => 'error', 'code' => '30005', 'msg' => 'login failed']);
        $client = new WebsocketClient(
            url: WebsocketClient::DEFAULT_PRIVATE_URL,
            apiKey: 'api-key',
            secretKey: 'secret-key',
            passphrase: 'passphrase',
            connection: $connection,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('code=30005');
        $client->connect();
    }

    public function test_websocket_login_rejects_a_non_success_login_event(): void
    {
        $connection = new FakeWebsocketConnection();
        $connection->received[] = json_encode(['event' => 'login', 'code' => '30005', 'msg' => 'login failed']);
        $client = new WebsocketClient(
            url: WebsocketClient::DEFAULT_PRIVATE_URL,
            apiKey: 'api-key',
            secretKey: 'secret-key',
            passphrase: 'passphrase',
            connection: $connection,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('code=30005');
        $client->connect();
    }

    public function test_websocket_connect_is_idempotent(): void
    {
        $connection = new FakeWebsocketConnection();
        $client = new WebsocketClient(WebsocketClient::DEFAULT_PUBLIC_URL, connection: $connection);

        $client->connect();
        $client->connect();

        $this->assertSame(1, $connection->connectCount);
    }

    public function test_websocket_subscribe_ignores_duplicate_channel(): void
    {
        $connection = new FakeWebsocketConnection();
        $client = new WebsocketClient(WebsocketClient::DEFAULT_PUBLIC_URL, connection: $connection);
        $channel = ['instType' => 'SPOT', 'topic' => 'ticker', 'symbol' => 'BTCUSDT'];

        $client->subscribe($channel);
        $client->subscribe($channel);

        $this->assertCount(1, $connection->sent);
        $this->assertSame(['op' => 'subscribe', 'args' => [$channel]], json_decode($connection->sent[0], true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_websocket_failed_subscription_is_not_restored_after_reconnect(): void
    {
        $connection = new FakeWebsocketConnection();
        $client = new WebsocketClient(WebsocketClient::DEFAULT_PUBLIC_URL, connection: $connection);
        $channel = ['instType' => 'SPOT', 'topic' => 'ticker', 'symbol' => 'BTCUSDT'];
        $client->connect();
        $connection->sendException = new ConnectionClosedException('socket closed');

        try {
            $client->subscribe($channel);
            $this->fail('Expected ConnectionClosedException');
        } catch (ConnectionClosedException) {
        }

        $connection->sendException = null;
        $connection->received = [
            new ConnectionClosedException('socket closed'),
            json_encode(['arg' => $channel, 'data' => []]),
        ];

        $client->listen(function () use ($client): void {
            $client->stop();
        });

        $this->assertSame(2, $connection->connectCount);
        $this->assertCount(0, $connection->sent);
    }

    public function test_websocket_reconnect_restores_active_channels_in_one_request(): void
    {
        $connection = new FakeWebsocketConnection();
        $client = new WebsocketClient(WebsocketClient::DEFAULT_PUBLIC_URL, connection: $connection);
        $channelOne = ['instType' => 'SPOT', 'topic' => 'ticker', 'symbol' => 'BTCUSDT'];
        $channelTwo = ['instType' => 'SPOT', 'topic' => 'ticker', 'symbol' => 'ETHUSDT'];

        $client->connect();
        $client->subscribe($channelOne);
        $client->subscribe($channelTwo);
        $connection->received = [
            new ConnectionClosedException('socket closed'),
            json_encode(['arg' => $channelOne, 'data' => []]),
        ];

        $client->listen(function () use ($client): void {
            $client->stop();
        });

        $this->assertSame(2, $connection->connectCount);
        $this->assertCount(3, $connection->sent);
        $this->assertSame([
            'op' => 'subscribe',
            'args' => [$channelOne, $channelTwo],
        ], json_decode($connection->sent[2], true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_websocket_retries_with_a_fresh_connection_when_resubscribe_fails(): void
    {
        $connection = new FakeWebsocketConnection();
        $client = new WebsocketClient(WebsocketClient::DEFAULT_PUBLIC_URL, connection: $connection);
        $channel = ['instType' => 'SPOT', 'topic' => 'ticker', 'symbol' => 'BTCUSDT'];

        $client->connect();
        $client->subscribe($channel);
        $connection->failOnSendAttempt = 2;
        $connection->received = [
            new ConnectionClosedException('socket closed'),
            json_encode(['arg' => $channel, 'data' => []]),
        ];

        $client->listen(function () use ($client): void {
            $client->stop();
        });

        $this->assertSame(3, $connection->connectCount);
        $this->assertSame(3, $connection->closeCount);
        $this->assertCount(2, $connection->sent);
    }

    public function test_websocket_unsubscribe_ignores_unknown_channel(): void
    {
        $connection = new FakeWebsocketConnection();
        $client = new WebsocketClient(WebsocketClient::DEFAULT_PUBLIC_URL, connection: $connection);

        $client->unsubscribe(['instType' => 'SPOT', 'topic' => 'ticker', 'symbol' => 'BTCUSDT']);

        $this->assertSame([], $connection->sent);
    }
}
