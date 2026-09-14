<?php
declare(strict_types=1);

namespace App\Controllers\Public_;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/** sitemap.xml and robots.txt generated from live content. */
final class SeoController extends BaseController
{
    public function sitemap(Request $request): Response
    {
        $base = rtrim((string)config('app.url', url('/')), '/');
        $db   = Database::instance();

        $urls = [
            ['loc' => $base . '/',         'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $base . '/services', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $base . '/team',     'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => $base . '/gallery',  'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => $base . '/blog',     'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => $base . '/contact',  'priority' => '0.6', 'changefreq' => 'yearly'],
            ['loc' => $base . '/booking',  'priority' => '1.0', 'changefreq' => 'monthly'],
        ];

        foreach ($db->select("SELECT slug, updated_at FROM services WHERE status='ACTIVE' AND deleted_at IS NULL") as $s) {
            $urls[] = [
                'loc' => $base . '/services/' . $s['slug'],
                'lastmod' => substr((string)$s['updated_at'], 0, 10),
                'priority' => '0.8', 'changefreq' => 'monthly',
            ];
        }
        foreach ($db->select("SELECT slug, published_at FROM posts WHERE status='PUBLISHED'") as $p) {
            $urls[] = [
                'loc' => $base . '/blog/' . $p['slug'],
                'lastmod' => substr((string)$p['published_at'], 0, 10),
                'priority' => '0.6', 'changefreq' => 'monthly',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            if (!empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n"
                 .  '    <priority>' . $u['priority'] . "</priority>\n  </url>\n";
        }
        $xml .= '</urlset>';

        return Response::make($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(Request $request): Response
    {
        $base = rtrim((string)config('app.url', url('/')), '/');
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /staff',
            'Disallow: /customer',
            'Disallow: /install',
            'Disallow: /api/',
            'Disallow: /login',
            '',
            'Sitemap: ' . $base . '/sitemap.xml',
            '',
        ]);

        return Response::make($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
