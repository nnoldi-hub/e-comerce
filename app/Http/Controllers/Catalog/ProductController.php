<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(Request $request, Product $product): View
    {
        // Doar produsele active și publicate sunt vizibile (restul: 404, fără a le dezvălui).
        abort_unless(
            $product->status === 'active' && $product->published_at !== null && $product->published_at->lte(now()),
            404,
        );

        $product->load(['media', 'brand', 'categories', 'variants.attributeValues']);

        $variants = $product->variants;
        $requested = (int) $request->query('variant');

        /** @var ProductVariant|null $variant */
        $variant = ($requested > 0 ? $variants->firstWhere('id', $requested) : null)
            ?? $variants->firstWhere('is_default', true)
            ?? $variants->first();

        $seo = is_array($product->seo) ? $product->seo : [];
        $description = (string) ($seo['description'] ?? Str::limit(trim(strip_tags((string) $product->description)), 160));

        return view('catalog.product', [
            'product' => $product,
            'variants' => $variants,
            'variant' => $variant,
            'metaTitle' => (string) ($seo['title'] ?? $product->name),
            'metaDescription' => $description,
            'structuredData' => $this->structuredData($product, $variant, $description),
        ]);
    }

    /**
     * Date structurate schema.org/Product (rich results în Google).
     *
     * @return array<string, mixed>
     */
    private function structuredData(Product $product, ?ProductVariant $variant, string $description): array
    {
        $media = $product->getFirstMedia('thumbnail') ?? $product->getFirstMedia('gallery');

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $description,
            'url' => route('catalog.product', $product),
        ];

        if ($media !== null) {
            $data['image'] = url($media->hasGeneratedConversion('large') ? $media->getUrl('large') : $media->getUrl());
        }

        if ($product->brand !== null) {
            $data['brand'] = ['@type' => 'Brand', 'name' => (string) data_get($product->brand, 'name')];
        }

        if ($variant !== null) {
            $data['sku'] = $variant->sku;
            $data['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'RON',
                'price' => number_format($variant->price / 100, 2, '.', ''),
                'availability' => $this->purchasable($variant)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => route('catalog.product', $product),
            ];
        }

        return $data;
    }

    private function purchasable(ProductVariant $variant): bool
    {
        return $variant->allow_backorder || ! $variant->track_inventory || $variant->available_stock > 0;
    }
}
