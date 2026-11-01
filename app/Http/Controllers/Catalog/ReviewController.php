<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** Statusurile de comandă care dovedesc o achiziție reală. */
    private const PURCHASE_STATUSES = ['paid', 'processing', 'shipped', 'delivered', 'completed'];

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless(Product::query()->active()->whereKey($product->id)->exists(), 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $userId = (int) $request->user()?->id;

        // O nouă trimitere înlocuiește recenzia veche și o trimite din nou la moderare.
        Review::query()->updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $userId],
            [
                'rating' => (int) $data['rating'],
                'title' => isset($data['title']) ? trim(strip_tags($data['title'])) ?: null : null,
                'body' => trim(strip_tags($data['body'])),
                'status' => Review::PENDING,
                'verified_purchase' => $this->hasPurchased($userId, $product),
                'moderated_at' => null,
            ],
        );

        return redirect(route('catalog.product', $product).'#recenzii')
            ->with('status', 'Mulțumim! Recenzia ta va apărea după ce este aprobată.');
    }

    private function hasPurchased(int $userId, Product $product): bool
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->whereIn('status', self::PURCHASE_STATUSES))
            ->whereHas('variant', fn ($q) => $q->where('product_id', $product->id))
            ->exists();
    }
}
