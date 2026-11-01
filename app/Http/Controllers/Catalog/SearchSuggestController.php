<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sugestii pentru căutarea din header: produse și categorii care se potrivesc cu
 * ce a tastat clientul. Date publice (aceleași ca în catalog), fără sesiune sau
 * date personale; limita de cereri e pe rută.
 */
class SearchSuggestController extends Controller
{
    private const MIN_LENGTH = 2;

    private const MAX_PRODUCTS = 6;

    private const MAX_CATEGORIES = 3;

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->query('q');
        $term = is_string($raw) ? Str::limit(trim($raw), 100, '') : '';

        $payload = mb_strlen($term) < self::MIN_LENGTH
            ? ['query' => $term, 'products' => [], 'categories' => []]
            : ['query' => $term, 'products' => $this->products($term), 'categories' => $this->categories($term)];

        return response()->json($payload)
            ->header('Cache-Control', 'private, max-age=30')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * @return list<array{name: string, url: string, price: string|null, image: string|null}>
     */
    private function products(string $term): array
    {
        $escaped = addcslashes($term, '%_\\');
        $like = '%'.$escaped.'%';

        $products = Product::query()
            ->active()
            ->whereHas('variants')
            ->with('media')
            ->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like));
            })
            // Întâi cele al căror nume începe cu textul tastat.
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$escaped.'%'])
            ->orderByDesc('published_at')
            ->limit(self::MAX_PRODUCTS)
            ->get();

        $rows = [];

        foreach ($products as $product) {
            $rows[] = [
                'name' => $product->name,
                'url' => route('catalog.product', $product, false),
                'price' => $product->min_price > 0 ? number_format($product->min_price / 100, 2, ',', '.').' RON' : null,
                'image' => $this->imageUrl($product),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function categories(string $term): array
    {
        $categories = Category::query()
            ->active()
            ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->orderBy('name')
            ->limit(self::MAX_CATEGORIES)
            ->get(['id', 'name', 'path']);

        $rows = [];

        foreach ($categories as $category) {
            if ($category->path === null) {
                continue;
            }

            $rows[] = [
                'name' => $category->name,
                'url' => route('catalog.category', ['path' => $category->path], false),
            ];
        }

        return $rows;
    }

    private function imageUrl(Product $product): ?string
    {
        $media = $product->getFirstMedia('thumbnail') ?? $product->getFirstMedia('gallery');

        if ($media === null) {
            return null;
        }

        foreach (['thumb', 'card'] as $conversion) {
            if ($media->hasGeneratedConversion($conversion)) {
                return $media->getUrl($conversion);
            }
        }

        return $media->getUrl();
    }
}
