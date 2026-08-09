<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget\API;

/**
 * Private order and position endpoints.
 *
 * Docs: https://www.bitget.com/api-doc/uta/trade/Place-Order
 */
class Trade extends BaseAPI
{
    /**
     * Submits a new spot, margin, or futures order.
     *
     * Docs: https://www.bitget.com/api-doc/uta/trade/Place-Order
     */
    public function placeOrder(array $params): array
    {
        return $this->client->request('POST', '/api/v3/trade/place-order', [], $this->withoutNulls($params));
    }

    /**
     * Amends the price, quantity, or TP/SL of an open order. $params must
     * include either orderId or clientOid.
     *
     * Docs: https://www.bitget.com/api-doc/uta/trade/Modify-Order
     */
    public function modifyOrder(array $params): array
    {
        return $this->client->request('POST', '/api/v3/trade/modify-order', [], $this->withoutNulls($params));
    }

    /**
     * Cancels a single open order. $params must include either orderId or
     * clientOid.
     *
     * Docs: https://www.bitget.com/api-doc/uta/trade/Cancel-Order
     */
    public function cancelOrder(array $params): array
    {
        return $this->client->request('POST', '/api/v3/trade/cancel-order', [], $this->withoutNulls($params));
    }

    /**
     * Lists currently unfilled/partially-filled orders.
     *
     * Docs: https://www.bitget.com/api-doc/uta/trade/Get-Order-Pending
     */
    public function getOpenOrders(array $filters = []): array
    {
        return $this->client->request('GET', '/api/v3/trade/unfilled-orders', $this->withoutNulls($filters));
    }

    /**
     * Lists historical (filled/cancelled) orders. The startTime/endTime
     * window may not exceed 30 days, within a 90-day lookback. $filters
     * must include 'category'.
     *
     * Docs: https://www.bitget.com/api-doc/uta/trade/Get-Order-History
     */
    public function getOrderHistory(array $filters): array
    {
        return $this->client->request('GET', '/api/v3/trade/history-orders', $this->withoutNulls($filters));
    }

    /**
     * Returns open futures positions for a product category, optionally
     * filtered by symbol and/or position side.
     *
     * Docs: https://www.bitget.com/api-doc/uta/trade/Get-Position
     */
    public function getPositions(string $category, ?string $symbol = null, ?string $posSide = null): array
    {
        return $this->client->request('GET', '/api/v3/position/current-position', $this->withoutNulls([
            'category' => $category,
            'symbol' => $symbol,
            'posSide' => $posSide,
        ]));
    }

    private function withoutNulls(array $params): array
    {
        return array_filter($params, static fn ($v) => $v !== null);
    }
}
