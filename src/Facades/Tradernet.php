<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Tradernet\Laravel\TradernetManager;
use Tradernet\Sdk\Tradernet as SdkTradernet;

/**
 * @method static \Tradernet\Sdk\Api\AlertsApi alerts()
 * @method static \Tradernet\Sdk\Api\AuthApi auth()
 * @method static \Tradernet\Sdk\Config\ClientConfig config()
 * @method static SdkTradernet connection(?string $name = null)
 * @method static \Tradernet\Sdk\Api\CpsApi cps()
 * @method static \Tradernet\Sdk\Api\CurrencyApi currency()
 * @method static string getDefaultConnection()
 * @method static \Tradernet\Sdk\Api\NewsApi news()
 * @method static \Tradernet\Sdk\Api\OrdersApi orders()
 * @method static \Tradernet\Sdk\Api\PortfolioApi portfolio()
 * @method static void purge(?string $name = null)
 * @method static \Tradernet\Sdk\Api\QuotesApi quotes()
 * @method static \Tradernet\Sdk\Api\ReferenceApi reference()
 * @method static \Tradernet\Sdk\Api\ReportsApi reports()
 * @method static array<string, mixed> request(string $command, array<string, mixed> $data = [], \Tradernet\Sdk\Transport\HttpMethod $method = \Tradernet\Sdk\Transport\HttpMethod::POST, bool $requiresSid = false)
 * @method static \Tradernet\Sdk\Api\SecuritySessionApi securitySessions()
 * @method static \Tradernet\Sdk\Auth\SessionManager sessions()
 * @method static \Tradernet\Sdk\Api\ShopApi shop()
 * @method static \Tradernet\Sdk\Api\StockListsApi stockLists()
 * @method static \Tradernet\Sdk\Api\TariffApi tariff()
 * @method static \Tradernet\Sdk\Transport\TransportInterface transport()
 * @method static \Tradernet\Sdk\Api\UserApi user()
 * @method static \Tradernet\Sdk\Ws\WebSocketClientInterface websocket()
 *
 * @see TradernetManager
 * @see SdkTradernet
 */
final class Tradernet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TradernetManager::class;
    }
}
