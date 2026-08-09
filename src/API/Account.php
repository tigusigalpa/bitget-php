<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\API;

/**
 * Private account endpoints.
 *
 * Docs: https://www.bitget.com/api-doc/uta/account/Get-Account
 */
class Account extends BaseAPI
{
    /**
     * Returns the unified account's aggregate equity, margin, and per-coin
     * balances.
     *
     * Docs: https://www.bitget.com/api-doc/uta/account/Get-Account
     */
    public function getAssets(): array
    {
        return $this->client->request('GET', '/api/v3/account/assets');
    }

    /**
     * Returns the unified account's mode plus per-symbol/coin leverage
     * configuration.
     *
     * Docs: https://www.bitget.com/api-doc/uta/account/Get-Account-Setting
     */
    public function getSettings(): array
    {
        return $this->client->request('GET', '/api/v3/account/settings');
    }

    /**
     * Configures leverage for futures or margin trading.
     *
     * Docs: https://www.bitget.com/api-doc/uta/account/Change-Leverage
     */
    public function setLeverage(
        string $category,
        ?string $symbol = null,
        ?string $leverage = null,
        ?string $coin = null,
        ?string $posSide = null,
        ?string $marginMode = null,
        ?string $longLeverage = null,
        ?string $shortLeverage = null,
    ): array {
        return $this->client->request('POST', '/api/v3/account/set-leverage', [], array_filter([
            'category' => $category,
            'symbol' => $symbol,
            'leverage' => $leverage,
            'coin' => $coin,
            'posSide' => $posSide,
            'marginMode' => $marginMode,
            'longLeverage' => $longLeverage,
            'shortLeverage' => $shortLeverage,
        ], static fn ($v) => $v !== null));
    }
}
