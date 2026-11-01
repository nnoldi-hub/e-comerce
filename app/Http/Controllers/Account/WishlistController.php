<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->active()
            ->whereIn('id', WishlistItem::query()->where('user_id', $request->user()?->id)->select('product_id'))
            ->with(['media', 'variants'])
            ->orderByDesc('id')
            ->paginate(12);

        return view('account.wishlist.index', ['products' => $products]);
    }

    /** Adaugă sau scoate produsul din favorite. */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $userId = (int) $request->user()?->id;

        $existing = WishlistItem::query()->where('user_id', $userId)->where('product_id', $product->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('status', 'Produsul a fost scos din favorite.');
        }

        abort_unless(Product::query()->active()->whereKey($product->id)->exists(), 404);

        WishlistItem::query()->create(['user_id' => $userId, 'product_id' => $product->id]);

        return back()->with('status', 'Produsul a fost adăugat la favorite.');
    }
}
