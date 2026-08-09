<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget;

/**
 * HMAC-SHA256 / Base64 request signer for the Bitget UTA v3 API.
 *
 * Docs: https://www.bitget.com/api-doc/uta/guide
 */
final class Signer
{
    public function __construct(private readonly string $secretKey)
    {
    }

    /**
     * Returns the current Unix timestamp in milliseconds, as a string.
     *
     * Docs: https://www.bitget.com/api-doc/uta/guide
     */
    public function generateTimestamp(): string
    {
        return (string) (int) round(microtime(true) * 1000);
    }

    /**
     * Signs a request. Pre-hash string: timestamp + METHOD + requestPath
     * [+ "?" + queryString] + body. requestPath must already include the
     * query string when one is present, since Bitget signs them together.
     *
     * Docs: https://www.bitget.com/api-doc/uta/guide
     */
    public function sign(string $timestamp, string $method, string $requestPath, string $body = ''): string
    {
        $prehash = $timestamp . strtoupper($method) . $requestPath . $body;
        $signature = hash_hmac('sha256', $prehash, $this->secretKey, true);

        return base64_encode($signature);
    }

    /**
     * Signs the WebSocket login pre-hash: timestamp + "GET" + "/user/verify".
     *
     * Docs: https://www.bitget.com/api-doc/uta/guide
     */
    public function signWsLogin(string $timestamp): string
    {
        return $this->sign($timestamp, 'GET', '/user/verify');
    }

    /**
     * Sorts query parameters ascending by key (required by Bitget's
     * signature algorithm) and builds a URL-encoded query string, skipping
     * null/empty values.
     */
    public static function buildQueryString(array $params): string
    {
        $filtered = array_filter($params, static fn ($value) => $value !== null && $value !== '');
        ksort($filtered);

        return http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    }
}
