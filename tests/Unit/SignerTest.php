<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\Tests\Unit;

use Tigusigalpa\Bitget\Signer;
use Tigusigalpa\Bitget\Tests\TestCase;

/**
 * Known-answer vectors computed independently via
 * `openssl dgst -sha256 -hmac`, covering a GET without query params, a GET
 * with query params, and a POST with a JSON body. The same vectors (same
 * secret/timestamp/paths) are also used in bitget-go's client_test.go, so
 * both SDKs are cross-validated against the same expected output.
 *
 * Docs: https://www.bitget.com/api-doc/uta/guide
 */
class SignerTest extends TestCase
{
    private Signer $signer;

    protected function setUp(): void
    {
        $this->signer = new Signer('test-secret-key');
    }

    public function test_sign_get_without_query(): void
    {
        $got = $this->signer->sign('1622185200000', 'GET', '/api/v3/account/assets');
        $this->assertSame('K74C/9e7Xifxt2o7mhHMrKVlCcS5+9ltdOp8IwDCeEc=', $got);
    }

    public function test_sign_get_with_query(): void
    {
        $got = $this->signer->sign('1622185200000', 'GET', '/api/v3/market/tickers?category=SPOT&symbol=BTCUSDT');
        $this->assertSame('uKM5T0Vj9c0Nt+nn8ALucHdTdnVMPCX4Hk/j1+lt6LI=', $got);
    }

    public function test_sign_post_with_json_body(): void
    {
        $body = '{"category":"SPOT","symbol":"BTCUSDT","side":"buy"}';
        $got = $this->signer->sign('1622185200000', 'POST', '/api/v3/trade/place-order', $body);
        $this->assertSame('PoEyXzTthV6aMBFloYTi5i1DHbf1m4nwXER8JR5W0AE=', $got);
    }

    public function test_sign_ws_login(): void
    {
        $got = $this->signer->signWsLogin('1622185200000');
        $this->assertSame('SEw02Qwy8zXY/tbcv5X722/TFqcGZkar/n+Pcvkz450=', $got);
    }

    public function test_build_query_string_sorts_ascending_by_key(): void
    {
        $got = Signer::buildQueryString(['symbol' => 'BTCUSDT', 'category' => 'SPOT']);
        $this->assertSame('category=SPOT&symbol=BTCUSDT', $got);
    }

    public function test_build_query_string_omits_null_and_empty_values(): void
    {
        $got = Signer::buildQueryString(['symbol' => 'BTCUSDT', 'cursor' => null, 'limit' => '']);
        $this->assertSame('symbol=BTCUSDT', $got);
    }

    public function test_generate_timestamp_is_milliseconds(): void
    {
        $timestamp = $this->signer->generateTimestamp();
        $this->assertMatchesRegularExpression('/^\d{13}$/', $timestamp);
    }
}
