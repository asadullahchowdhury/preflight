<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml. Served by the app (not as static files) so they carry this deployment's URL.
 */
class SeoController extends Controller
{
    /** Signed-in areas crawlers have no business in. */
    private const PRIVATE_PATHS = [
        '/dashboard', '/repositories/', '/analyses/', '/settings/', '/admin/',
        '/waitlist', '/github/', '/installations/', '/auth/', '/dev/', '/api/',
    ];

    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->isProduction()) {
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = "Disallow: {$path}";
            }
        } else {
            // Staging and local copies stay out of search results.
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = [['loc' => url('/'), 'priority' => '1.0']];
        foreach (array_keys(DocsController::PAGES) as $slug) {
            $urls[] = ['loc' => route('docs', $slug), 'priority' => '0.8'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc><priority>'.$url['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
