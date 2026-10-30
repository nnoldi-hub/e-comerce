<?php

namespace App\Http\Controllers\Checkout;

use App\Domain\Account\AddressBook;
use App\Domain\Cart\Services\CartService;
use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Checkout\Exceptions\CheckoutException;
use App\Domain\Pricing\PriceCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PriceCalculator $prices,
        private readonly PlaceOrderAction $placeOrder,
        private readonly AddressBook $addressBook,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->getCurrentCart();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Coșul este gol.');
        }

        // Clientul autentificat își alege o adresă din carnet (?adresa=ID); implicit, cea implicită.
        $user = $request->user();
        $addresses = $user !== null ? $this->addressBook->forUser($user->id) : collect();
        $selected = $addresses->firstWhere('id', (int) $request->query('adresa')) ?? $addresses->firstWhere('is_default', true);

        return view('checkout.index', [
            'savedAddresses' => $addresses,
            'selectedAddressId' => $selected?->id,
            'prefill' => $this->addressBook->prefill($selected),
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
            $data = $request->toData($userId);
            $order = $this->placeOrder->execute($cart, $data);
        } catch (CheckoutException $exception) {
            return back()
                ->withInput()
                ->withErrors([$exception->field() => $exception->userMessage()]);
        }

        $request->session()->flash('order_number', $order->number);

        // O eroare la salvarea adresei nu trebuie să strice o comandă deja plasată.
        if ($userId !== null && $request->boolean('save_address')) {
            try {
                $this->addressBook->rememberFromCheckout($userId, $data->shippingAddress, $data->billingAddress);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $payment = Payment::query()->where('order_id', $order->id)->latest('id')->first();
        $redirectUrl = $payment?->payload['redirect_url'] ?? null;

        if (is_string($redirectUrl) && $redirectUrl !== '') {
            return redirect()->away($redirectUrl);
        }

        return redirect()->route('checkout.success');
    }

    public function success(Request $request): View|RedirectResponse
    {
        $number = $request->session()->get('order_number');

        if (! $number) {
            return redirect()->route('cart.index');
        }

        $order = Order::query()
            ->where('number', $number)
            ->with('items')
            ->first();

        if ($order === null) {
            return redirect()->route('cart.index');
        }

        return view('checkout.success', ['order' => $order]);
    }
}
