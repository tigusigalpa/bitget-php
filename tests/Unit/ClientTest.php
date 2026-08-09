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
use Tigusigalpa\Bitget\Exceptions\RateLimitException;
use Tigusigalpa\Bitget\Tests\TestCase;

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
}
