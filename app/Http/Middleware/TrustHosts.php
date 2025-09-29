<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Engedélyezett hostok regex mintái.
     * 'local' környezetben kikapcsoljuk (null) az ellenőrzést.
     */
    public function hosts(): array|null
    {
        if (app()->environment('local')) {
            return null; // dev: ne korlátozzuk a hostot
        }

        return [
            $this->allSubdomainsOfApplicationUrl(),   // APP_URL host + aldomének
            'localhost',
            '127\.0\.0\.1',
            '192\.168\.\d{1,3}\.\d{1,3}',             // privát 192.168.x.x
            '10\.\d{1,3}\.\d{1,3}\.\d{1,3}',          // privát 10.x.x.x
            '172\.(?:1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}', // privát 172.16–31.x.x
        ];
    }
}
