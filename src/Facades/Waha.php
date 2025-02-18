<?php

namespace Rosandi\WAHA\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rosandi\WAHA\Services\WahaConfig host(string $host)
 * @method static \Rosandi\WAHA\Services\WahaConfig apikey(string $apikey)
 * @method static \Rosandi\WAHA\Services\WahaConfig user(string $user)
 * @method static \Rosandi\WAHA\Services\WahaConfig password(string $password)
 * @method static \Rosandi\WAHA\Services\WahaConfig to(string $to)
 * @method static array sessions()
 */
class Waha extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'waha';
    }
}
