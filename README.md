# Bitget PHP SDK

![Bitget PHP Laravel SDK](https://i.postimg.cc/BncJyFmW/bitget-php-sdk-github.jpg)

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-777bb4)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-ff2d20)](composer.json)
[![Tests](https://github.com/tigusigalpa/bitget-php/actions/workflows/test.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/bitget-php/actions/workflows/test.yml)
[![Coverage](https://github.com/tigusigalpa/bitget-php/actions/workflows/coverage.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/bitget-php/actions/workflows/coverage.yml)
[![CodeQL](https://github.com/tigusigalpa/bitget-php/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/bitget-php/actions/workflows/codeql.yml)
[![Security Audit](https://github.com/tigusigalpa/bitget-php/actions/workflows/security.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/bitget-php/actions/workflows/security.yml)
[![Codecov](https://codecov.io/gh/tigusigalpa/bitget-php/graph/badge.svg)](https://codecov.io/gh/tigusigalpa/bitget-php)

A production-grade PHP SDK for the [Bitget Unified Trading Account (UTA) API v3](https://www.bitget.com/api-doc/uta/intro), with seamless Laravel 10-13 integration. Phase 1 covers Market, Account, and Trade REST services plus a reconnecting WebSocket client with a pluggable transport.

**Package:** a matching Go SDK is available at [tigusigalpa/bitget-go](https://github.com/tigusigalpa/bitget-go).

## What's inside

- PHP 8.2+, `declare(strict_types=1)` everywhere, readonly constructor properties
- Strings for every price/quantity/PnL/fee field — no float rounding errors
- Guzzle-based HTTP transport with an injectable `GuzzleHttp\Client` for tests/proxies
- PSR-3 logging (defaults to a no-op `NullLogger`); credentials are never logged
- A typed exception hierarchy (`AuthenticationException`, `RateLimitException`, `InvalidParameterException`, `InsufficientFundsException`, `OrderNotFoundException`) carrying Bitget's raw error code
- A `WebsocketClient` with pluggable transport (`ConnectionInterface`) — ships with a synchronous `textalk/websocket` adapter; implement the interface yourself to run under ReactPHP, Amp, or Laravel Octane's event loop
- Laravel service provider, publishable config, and `Bitget` facade — auto-discovered, but the SDK has no hard `illuminate/*` dependency outside Laravel apps

## Install

```bash
composer require tigusigalpa/bitget-php
```

### Laravel setup

The service provider and `Bitget` facade are auto-discovered. Publish the config file:

```bash
php artisan vendor:publish --tag=bitget-config
```

```env
BITGET_API_KEY=your-api-key
BITGET_SECRET_KEY=your-secret-key
BITGET_PASSPHRASE=your-passphrase
BITGET_DEMO=false
```

```php
use Tigusigalpa\Bitget\Facades\Bitget;

$tickers = Bitget::market()->getTickers('SPOT', 'BTCUSDT');
```

### Plain PHP (no Laravel)

```php
use Tigusigalpa\Bitget\Client;

$client = new Client(
    apiKey: getenv('BITGET_API_KEY'),
    secretKey: getenv('BITGET_SECRET_KEY'),
    passphrase: getenv('BITGET_PASSPHRASE'),
);

$tickers = $client->market()->getTickers('SPOT', 'BTCUSDT');
```

## Configuration

| `config/bitget.php` key | Env var | Default |
|---|---|---|
| `api_key` | `BITGET_API_KEY` | `''` |
| `secret_key` | `BITGET_SECRET_KEY` | `''` |
| `passphrase` | `BITGET_PASSPHRASE` | `''` |
| `demo` | `BITGET_DEMO` | `false` |
| `base_url` | `BITGET_BASE_URL` | `https://api.bitget.com` |
| `locale` | `BITGET_LOCALE` | `en-US` |

## REST API coverage (Phase 1)

| Category | Methods | Docs |
|---|---|---|
| Market (public) | `getInstruments`, `getTickers`, `getOrderBook` | [Instruments](https://www.bitget.com/api-doc/uta/public/Instruments) · [Tickers](https://www.bitget.com/api-doc/uta/public/Tickers) · [OrderBook](https://www.bitget.com/api-doc/uta/public/OrderBook) |
| Account (private) | `getAssets`, `getSettings`, `setLeverage` | [Get-Account](https://www.bitget.com/api-doc/uta/account/Get-Account) · [Get-Account-Setting](https://www.bitget.com/api-doc/uta/account/Get-Account-Setting) · [Change-Leverage](https://www.bitget.com/api-doc/uta/account/Change-Leverage) |
| Trade (private) | `placeOrder`, `modifyOrder`, `cancelOrder`, `getOpenOrders`, `getOrderHistory`, `getPositions` | [Place-Order](https://www.bitget.com/api-doc/uta/trade/Place-Order) · [Modify-Order](https://www.bitget.com/api-doc/uta/trade/Modify-Order) · [Cancel-Order](https://www.bitget.com/api-doc/uta/trade/Cancel-Order) · [Get-Order-Pending](https://www.bitget.com/api-doc/uta/trade/Get-Order-Pending) · [Get-Order-History](https://www.bitget.com/api-doc/uta/trade/Get-Order-History) · [Get-Position](https://www.bitget.com/api-doc/uta/trade/Get-Position) |

Full mapping with HTTP methods and paths: [docs/endpoints.md](docs/endpoints.md).

## WebSocket

```php
use Tigusigalpa\Bitget\WebsocketClient;

$ws = new WebsocketClient(WebsocketClient::DEFAULT_PUBLIC_URL);
$ws->connect();
$ws->subscribe(['instType' => 'SPOT', 'topic' => 'ticker', 'symbol' => 'BTCUSDT']);

$ws->listen(function (array $push) {
    echo json_encode($push), PHP_EOL;
});
```

For private channels (e.g. order fills), pass credentials to the constructor — `connect()` authenticates automatically:

```php
$ws = new WebsocketClient(
    url: WebsocketClient::DEFAULT_PRIVATE_URL,
    apiKey: config('bitget.api_key'),
    secretKey: config('bitget.secret_key'),
    passphrase: config('bitget.passphrase'),
);
$ws->connect();
$ws->subscribe(['instType' => 'UTA', 'topic' => 'fast-fill', 'symbol' => 'default']);
$ws->listen(fn (array $push) => /* handle fill */ null);
```

`connect()` is safe to call repeatedly while the socket is open. `subscribe()` is idempotent per channel, preventing duplicate subscription frames and unnecessary quota use. `listen()` blocks the current process, answers Bitget's text-frame ping/pong heartbeat, and — on an unexpected disconnect — reconnects with exponential backoff (1s → 60s cap) and restores every active channel in one subscription frame. The default transport (`TextalkConnection`) is synchronous; to run under a non-blocking event loop (ReactPHP, Amp, Laravel Octane), implement `Tigusigalpa\Bitget\WebSocket\ConnectionInterface` and pass it as `WebsocketClient`'s `$connection` constructor argument.

Implemented private channel: [`fast-fill`](https://www.bitget.com/api-doc/uta/websocket/private/Fast-Fill-Channel). Other channels work through the same `subscribe()`/`listen()` API; see [docs/endpoints.md](docs/endpoints.md).

## Demo trading

Set `demo: true` (or `BITGET_DEMO=true`) together with a **Demo API key** from the Bitget console to send `paptrading: 1` on every REST request, or use `WebsocketClient::DEMO_PUBLIC_URL`/`DEMO_PRIVATE_URL` for WebSocket. Always validate new code against demo credentials before pointing it at a live account:

```php
$client = new Client(
    apiKey: config('bitget.api_key'),
    secretKey: config('bitget.secret_key'),
    passphrase: config('bitget.passphrase'),
    demoTrading: true,
);

// Guard order placement behind an explicit opt-in — never wire this to
// production credentials without removing the gate deliberately.
if (getenv('BITGET_ENABLE_TRADING') === '1') {
    $client->trade()->placeOrder([
        'category' => 'SPOT',
        'symbol' => 'BTCUSDT',
        'side' => 'buy',
        'orderType' => 'limit',
        'price' => '10000', // deliberately far below market so it won't fill
        'qty' => '0.001',
    ]);
}
```

## Errors

```php
use Tigusigalpa\Bitget\Exceptions\AuthenticationException;
use Tigusigalpa\Bitget\Exceptions\InsufficientFundsException;
use Tigusigalpa\Bitget\Exceptions\RateLimitException;
use Tigusigalpa\Bitget\Exceptions\BitgetException;

try {
    $client->trade()->placeOrder([...]);
} catch (AuthenticationException $e) {
    // invalid API key/secret/passphrase
} catch (InsufficientFundsException $e) {
    // not enough balance/margin
} catch (RateLimitException $e) {
    // back off and retry
} catch (BitgetException $e) {
    // anything else; $e->bitgetCode / $e->rawResponse are available
}
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

Unit tests run fully offline against a mocked Guzzle transport (`MockHandler`) — no network access or credentials required.

## Contributing

1. Fork and branch off `main`.
2. Add/update tests for any behavior change (`vendor/bin/phpunit` must pass).
3. Every public method must include a `Docs:` line in its docblock linking to the exact Bitget API documentation page it implements.
4. Update [docs/endpoints.md](docs/endpoints.md) for new endpoints.

## Security

Found a vulnerability? Email sovletig@gmail.com directly — please don't open a public issue.

## License

MIT. See [LICENSE](LICENSE).

## Author

Igor Sazonov — [@tigusigalpa](https://github.com/tigusigalpa) — sovletig@gmail.com

## Links

- [Bitget UTA API docs](https://www.bitget.com/api-doc/uta/intro)
- [Repository](https://github.com/tigusigalpa/bitget-php)
- [Issues](https://github.com/tigusigalpa/bitget-php/issues)

---

*Not affiliated with Bitget. Test on demo before going live.*
