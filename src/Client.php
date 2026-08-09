<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tigusigalpa\Bitget\Exceptions\AuthenticationException;
use Tigusigalpa\Bitget\Exceptions\BitgetException;
use Tigusigalpa\Bitget\Exceptions\InsufficientFundsException;
use Tigusigalpa\Bitget\Exceptions\InvalidParameterException;
use Tigusigalpa\Bitget\Exceptions\OrderNotFoundException;
use Tigusigalpa\Bitget\Exceptions\RateLimitException;

/**
 * Low-level authenticated HTTP transport for the Bitget UTA v3 API, plus
 * factory methods for every implemented service.
 *
 * Docs: https://www.bitget.com/api-doc/uta/intro
 */
class Client
{
    public const DEFAULT_BASE_URL = 'https://api.bitget.com';

    private const MAX_RESPONSE_BODY_BYTES = 10485760;

    protected HttpClient $httpClient;
    protected LoggerInterface $logger;
    protected Signer $signer;

    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $secretKey,
        protected readonly string $passphrase,
        protected readonly bool $demoTrading = false,
        protected readonly string $baseUrl = self::DEFAULT_BASE_URL,
        protected readonly string $locale = 'en-US',
        ?HttpClient $httpClient = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = $httpClient ?? new HttpClient(['base_uri' => $this->baseUrl, 'timeout' => 15, 'verify' => true]);
        $this->logger = $logger ?? new NullLogger();
        $this->signer = new Signer($this->secretKey);
    }

    public function market(): API\Market
    {
        return new API\Market($this);
    }

    public function account(): API\Account
    {
        return new API\Account($this);
    }

    public function trade(): API\Trade
    {
        return new API\Trade($this);
    }

    /**
     * Issues an unauthenticated request against a public endpoint. No
     * signature headers are sent.
     */
    public function requestPublic(string $method, string $path, array $query = []): array
    {
        return $this->request($method, $path, $query, null, false);
    }

    /**
     * Issues an authenticated request, signing it with the configured API
     * credentials.
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, bool $signed = true): array
    {
        $queryString = Signer::buildQueryString($query);
        $requestPath = $path . ($queryString !== '' ? '?' . $queryString : '');
        try {
            $bodyJson = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        } catch (\JsonException $e) {
            throw new BitgetException('ENCODE_ERROR', 'Failed to encode Bitget request body', '', $e);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'locale' => $this->locale,
        ];

        if ($signed) {
            $timestamp = $this->signer->generateTimestamp();
            $signature = $this->signer->sign($timestamp, $method, $requestPath, $bodyJson);
            $headers['ACCESS-KEY'] = $this->apiKey;
            $headers['ACCESS-SIGN'] = $signature;
            $headers['ACCESS-TIMESTAMP'] = $timestamp;
            $headers['ACCESS-PASSPHRASE'] = $this->passphrase;
            if ($this->demoTrading) {
                $headers['paptrading'] = '1';
            }
        }

        $this->logger->debug('Bitget request', ['method' => $method, 'path' => $requestPath]);

        try {
            // The URI is built manually (rather than via Guzzle's 'query'
            // option) so the query string sent on the wire is byte-identical
            // to the one used in the signature above.
            $response = $this->httpClient->request($method, $requestPath, [
                'headers' => $headers,
                'body' => $bodyJson !== '' ? $bodyJson : null,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new BitgetException('NETWORK_ERROR', $e->getMessage(), '', $e);
        }

        $rawBody = $this->readResponseBody($response->getBody());
        $status = $response->getStatusCode();

        if ($status === 429) {
            throw new RateLimitException('429', 'HTTP 429 Too Many Requests', $rawBody);
        }

        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            if ($status < 200 || $status >= 300) {
                throw new BitgetException((string) $status, 'Unexpected HTTP status ' . $status, $rawBody, $e);
            }
            throw new BitgetException('DECODE_ERROR', 'Failed to decode Bitget response body', $rawBody, $e);
        }
        if (! is_array($data)) {
            throw new BitgetException('DECODE_ERROR', 'Failed to decode Bitget response body', $rawBody);
        }

        $code = (string) ($data['code'] ?? '');
        if ($code !== '' && $code !== '00000') {
            $this->throwException($code, (string) ($data['msg'] ?? 'Unknown error'), $rawBody);
        }
        if ($status < 200 || $status >= 300) {
            throw new BitgetException((string) $status, 'Unexpected HTTP status ' . $status, $rawBody);
        }

        return $data['data'] ?? [];
    }

    private function readResponseBody(StreamInterface $body): string
    {
        $rawBody = '';
        while (! $body->eof()) {
            $rawBody .= $body->read(8192);
            if (strlen($rawBody) > self::MAX_RESPONSE_BODY_BYTES) {
                throw new BitgetException('RESPONSE_TOO_LARGE', 'Bitget response body exceeds ' . self::MAX_RESPONSE_BODY_BYTES . ' bytes');
            }
        }

        return $rawBody;
    }

    protected function throwException(string $code, string $message, string $rawResponse): void
    {
        $exceptionClass = match (true) {
            in_array($code, ['40001', '40002', '40003', '40004', '40005', '40006', '40009', '40012', '40014', '40017', '40037'], true) => AuthenticationException::class,
            in_array($code, ['429', '40429', '30007'], true) => RateLimitException::class,
            in_array($code, ['40018', '40019', '40020', '40021', '40022', '40023', '22001'], true) => InvalidParameterException::class,
            in_array($code, ['43012', '45006'], true) => InsufficientFundsException::class,
            in_array($code, ['43025', '43001'], true) => OrderNotFoundException::class,
            default => BitgetException::class,
        };

        throw new $exceptionClass($code, $message, $rawResponse);
    }
}
