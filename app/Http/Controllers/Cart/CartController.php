<?php

namespace App\Http\Controllers\Cart;

use App\Domain\Cart\Services\CartService;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): View
    {
        $cart = $this->cart->getCurrentCart();
        // Imaginile produselor din coș (evită interogări lazy, interzise în dezvoltare).
        $cart->loadMissing('items.variant.product.media');

        return view('cart.index', [
            'cart' => $cart,
            'subtotal' => $this->cart->subtotal($cart),
        ]);
    }

    public function add(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $this->cart->addItem($variant, $data['quantity'] ?? 1);

        return back()->with('status', 'Produsul a fost adăugat în coș.');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->ensureItemBelongsToCurrentCart($item);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $this->cart->updateItem($item, $data['quantity']);

        return back()->with('status', 'Coșul a fost actualizat.');
    }

    public function remove(CartItem $item): RedirectResponse
    {
        $this->ensureItemBelongsToCurrentCart($item);

        $this->cart->removeItem($item);

        return back()->with('status', 'Produsul a fost eliminat din coș.');
    }

    private function ensureItemBelongsToCurrentCart(CartItem $item): void
    {
        abort_unless(
            (int) $item->cart_id === (int) $this->cart->getCurrentCart()->id,
            403
        );
    }
}
