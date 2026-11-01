<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Bestsellers;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->active()
            ->with(['media', 'variants'])
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        $ids = app(Bestsellers::class)->ids();

        $bestsellers = $ids === []
            ? collect()
            : Product::query()->active()->whereIn('id', $ids)->with(['media', 'variants'])->get()
                ->sortBy(fn (Product $product): int => (int) array_search($product->id, $ids, true))
                ->values();

        return view('home.index', ['products' => $products, 'bestsellers' => $bestsellers]);
    }
}
