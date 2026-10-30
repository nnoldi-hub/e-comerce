<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->active()
            ->with('media')
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        return view('home.index', ['products' => $products]);
    }
}
