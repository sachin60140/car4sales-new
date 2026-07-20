<?php

namespace App\Domain\PublicWebsite\Services;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;

/**
 * Builds the XML sitemap from static routes, published vehicles and branches.
 */
class SitemapGenerator
{
    private const STATIC_PATHS = [
        ['/', '1.0', 'daily'],
        ['/cars', '0.9', 'hourly'],
        ['/sell-your-car', '0.8', 'weekly'],
        ['/finance', '0.6', 'monthly'],
        ['/about', '0.5', 'monthly'],
        ['/branches', '0.6', 'monthly'],
        ['/contact', '0.5', 'monthly'],
        ['/reviews', '0.5', 'weekly'],
        ['/faqs', '0.5', 'monthly'],
        ['/privacy-policy', '0.3', 'yearly'],
        ['/terms', '0.3', 'yearly'],
        ['/refund-policy', '0.3', 'yearly'],
        ['/disclaimer', '0.3', 'yearly'],
    ];

    public function xml(): string
    {
        $urls = [];

        foreach (self::STATIC_PATHS as [$path, $priority, $freq]) {
            $urls[] = $this->url(url($path), now()->toAtomString(), $freq, $priority);
        }

        Vehicle::query()->published()->whereNotNull('slug')
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get(['slug', 'updated_at'])
            ->each(function (Vehicle $v) use (&$urls) {
                $urls[] = $this->url(url("/cars/{$v->slug}"), $v->updated_at->toAtomString(), 'weekly', '0.7');
            });

        Branch::query()->where('is_active', true)->whereNotNull('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (Branch $b) use (&$urls) {
                $urls[] = $this->url(url("/branches/{$b->slug}"), $b->updated_at->toAtomString(), 'monthly', '0.5');
            });

        $body = implode("\n", $urls);

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$body."\n"
            .'</urlset>';
    }

    public function robots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /settings',
            'Disallow: /api',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);
    }

    private function url(string $loc, string $lastmod, string $freq, string $priority): string
    {
        return "  <url><loc>".e($loc)."</loc><lastmod>{$lastmod}</lastmod>"
            ."<changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>";
    }
}
