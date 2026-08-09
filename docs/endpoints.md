# Endpoint Coverage Map

Every SDK method below links to its exact Bitget UTA v3 documentation page.
Anything not listed here is **not implemented** in this Phase 1 release.

## Market (Public)

| SDK Method | HTTP | Path | Docs |
|---|---|---|---|
| `Market::getInstruments()` | GET | `/api/v3/market/instruments` | https://www.bitget.com/api-doc/uta/public/Instruments |
| `Market::getTickers()` | GET | `/api/v3/market/tickers` | https://www.bitget.com/api-doc/uta/public/Tickers |
| `Market::getOrderBook()` | GET | `/api/v3/market/orderbook` | https://www.bitget.com/api-doc/uta/public/OrderBook |

## Account (Private)

| SDK Method | HTTP | Path | Docs |
|---|---|---|---|
| `Account::getAssets()` | GET | `/api/v3/account/assets` | https://www.bitget.com/api-doc/uta/account/Get-Account |
| `Account::getSettings()` | GET | `/api/v3/account/settings` | https://www.bitget.com/api-doc/uta/account/Get-Account-Setting |
| `Account::setLeverage()` | POST | `/api/v3/account/set-leverage` | https://www.bitget.com/api-doc/uta/account/Change-Leverage |

## Trade (Private)

| SDK Method | HTTP | Path | Docs |
|---|---|---|---|
| `Trade::placeOrder()` | POST | `/api/v3/trade/place-order` | https://www.bitget.com/api-doc/uta/trade/Place-Order |
| `Trade::modifyOrder()` | POST | `/api/v3/trade/modify-order` | https://www.bitget.com/api-doc/uta/trade/Modify-Order |
| `Trade::cancelOrder()` | POST | `/api/v3/trade/cancel-order` | https://www.bitget.com/api-doc/uta/trade/Cancel-Order |
| `Trade::getOpenOrders()` | GET | `/api/v3/trade/unfilled-orders` | https://www.bitget.com/api-doc/uta/trade/Get-Order-Pending |
| `Trade::getOrderHistory()` | GET | `/api/v3/trade/history-orders` | https://www.bitget.com/api-doc/uta/trade/Get-Order-History |
| `Trade::getPositions()` | GET | `/api/v3/position/current-position` | https://www.bitget.com/api-doc/uta/trade/Get-Position |

## WebSocket

| Channel | Type | Docs |
|---|---|---|
| `fast-fill` | Private | https://www.bitget.com/api-doc/uta/websocket/private/Fast-Fill-Channel |

`WebsocketClient::subscribe()`/`listen()` are channel-agnostic — they work
with any public or private channel Bitget exposes, including ones not
listed above; you decode the push payload array yourself for anything
beyond `fast-fill`.

## Not implemented (Phase 1)

Everything else in the UTA v3 API surface — including, but not limited to:
Asset/transfer endpoints, Trading Bot, Copy Trading, RFQ, Rubik market
statistics, Spread trading, Affiliate, Fiat, Finance/earn products, batch
order endpoints, plan/trigger (conditional) orders, and all other public and
private WebSocket channels (candles, trades, order book, account, position,
order, and public trade channels).

Contributions adding coverage are welcome.
