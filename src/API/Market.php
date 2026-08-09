<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\API;

/**
 * Public market-data endpoints.
 *
 * Docs: https://www.bitget.com/api-doc/uta/public/Instruments
 */
class Market extends BaseAPI
{
    /**
     * Returns trading-pair specifications for a product category,
     * optionally filtered to a single symbol.
     *
     * Docs: https://www.bitget.com/api-doc/uta/public/Instruments
     */
    public function getInstruments(string $category, ?string $symbol = null): array
    {
        return $this->client->requestPublic('GET', '/api/v3/market/instruments', [
            'category' => $category,
            'symbol' => $symbol,
        ]);
    }

    /**
     * Returns 24h market statistics for a product category, optionally
     * filtered to a single symbol.
     *
     * Docs: https://www.bitget.com/api-doc/uta/public/Tickers
     */
    public function getTickers(string $category, ?string $symbol = null): array
    {
        return $this->client->requestPublic('GET', '/api/v3/market/tickers', [
            'category' => $category,
            'symbol' => $symbol,
        ]);
    }

    /**
     * Returns the bid/ask depth snapshot for a symbol. $limit controls the
     * depth level (default 5, max 1000).
     *
     * Docs: https://www.bitget.com/api-doc/uta/public/OrderBook
     */
    public function getOrderBook(string $category, string $symbol, ?string $limit = null): array
    {
        return $this->client->requestPublic('GET', '/api/v3/market/orderbook', [
            'category' => $category,
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }
}
