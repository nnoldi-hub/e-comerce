<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /** Limita protocolului sitemap este 50.000 de URL-uri per fișier. */
    private const MAX_URLS = 50000;

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => null],
            ['loc' => route('catalog.index'), 'lastmod' => null],
        ];

        Category::query()->active()->select(['id', 'path', 'updated_at'])->orderBy('id')
            ->each(function (Category $category) use (&$urls): void {
                $urls[] = [
                    'loc' => route('catalog.category', ['path' => $category->path]),
                    'lastmod' => $category->updated_at?->toAtomString(),
                ];
            });

        ContentPage::query()->published()->select(['id', 'slug', 'updated_at'])->orderBy('id')
            ->each(function (ContentPage $page) use (&$urls): void {
                $urls[] = [
                    'loc' => route('pages.show', $page->slug),
                    'lastmod' => $page->updated_at?->toAtomString(),
                ];
            });

        Product::query()->active()->whereHas('variants')->select(['id', 'slug', 'updated_at'])->orderBy('id')
            ->lazyById(500)
            ->each(function (Product $product) use (&$urls): bool {
                $urls[] = [
                    'loc' => route('catalog.product', ['product' => $product->slug]),
                    'lastmod' => $product->updated_at?->toAtomString(),
                ];

                return count($urls) < self::MAX_URLS;
            });

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach (array_slice($urls, 0, self::MAX_URLS) as $url) {
            $out .= '  <url><loc>'.e($url['loc']).'</loc>';

            if ($url['lastmod'] !== null) {
                $out .= '<lastmod>'.e($url['lastmod']).'</lastmod>';
            }

            $out .= "</url>\n";
        }

        return $out.'</urlset>'."\n";
    }
}
