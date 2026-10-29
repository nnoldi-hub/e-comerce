<?php

namespace App\Http\Controllers\Checkout;

use App\Domain\Cart\Services\CartService;
use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Checkout\Exceptions\CheckoutException;
use App\Domain\Pricing\PriceCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\ShippingMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PriceCalculator $prices,
        private readonly PlaceOrderAction $placeOrder,
    ) {}

    public function index(): View|RedirectResponse
    {
        $cart = $this->cart->getCurrentCart();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Coșul este gol.');
        }

        return view('checkout.index', [
            'cart' => $cart,
            'totals' => $this->prices->calculate($cart),
            'shippingMethods' => ShippingMethod::query()->where('is_active', true)->orderBy('position')->get(),
            // Se generează la afișarea formularului și se trimite înapoi la submit.
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $cart = $this->cart->getCurrentCart();
        $userId = Auth::id() !== null ? (int) Auth::id() : null;

        try {
            $order = $this->placeOrder->execute($cart, $request->toData($userId));
        } catch (CheckoutException $exception) {
            return back()
                ->withInput()
                ->withErrors([$exception->field() => $exception->userMessage()]);
        }

        $request->session()->flash('order_number', $order->number);

        return redirect()->route('checkout.success');
    }

    public function success(Request $request): View|RedirectResponse
    {
        $number = $request->session()->get('order_number');

        if (! $number) {
            return redirect()->route('cart.index');
        }

        return view('checkout.success', ['orderNumber' => $number]);
    }
}
