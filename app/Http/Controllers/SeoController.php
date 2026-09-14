<?php

namespace App\Http\Controllers;

use App\Support\CompanyServices;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * A publikus honlap oldalait engedélyezzük, a belső (kioszk/admin/szerviz)
     * felületeket kizárjuk a keresőrobotok elől.
     */
    public function robots(): Response
    {
        $disallow = [
            '/admin',
            '/app',
            '/monitor',
            '/jump-codes',
            '/login',
            '/dashboard',
            '/profile',
            '/devices',
            '/machines',
            '/time-entries',
            '/scheduler',
            '/my-attendance-sheet',
        ];

        $lines = ['User-agent: *'];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . route('sitemap');

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('szolgaltatasok.index'), 'priority' => '0.8'],
            ['loc' => route('oktatas'), 'priority' => '0.6'],
            ['loc' => route('kapcsolat'), 'priority' => '0.6'],
        ];

        foreach (CompanyServices::all() as $service) {
            $urls[] = [
                'loc' => route('szolgaltatasok.show', $service['slug']),
                'priority' => '0.7',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . e($url['loc']) . "</loc>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
