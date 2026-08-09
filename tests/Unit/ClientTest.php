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
use Tigusigalpa\Bitget\WebSocket\ConnectionInterface;
use Tigusigalpa\Bitget\WebSocket\TextalkConnection;

final class FakeWebsocketConnection implements ConnectionInterface
{
    public array $sent = [];
    public array $received = [];

    public function connect(string $url): void
    {
    }

    public function send(string $payload): void
    {
        $this->sent[] = $payload;
    }

    public function receive(): ?string
    {
        return array_shift($this->received);
    }

    public function close(): void
    {
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
}
