<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Comenzile clientului autentificat. Filtrarea după `user_id` este explicită
 * (OrderPolicy::view permite și personalului să vadă comenzi); o comandă a altcuiva
 * răspunde 404, ca să nu confirme că numărul există.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return view('account.orders.index', [
            'orders' => Order::query()
                ->where('user_id', $user->id)
                ->orderByDesc('placed_at')->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function show(Request $request, string $number): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('number', $number)
            ->with(['items', 'statusHistories'])
            ->firstOrFail();

        return view('account.orders.show', [
            'order' => $order,
            'shipment' => Shipment::query()->where('order_id', $order->id)->latest('id')->first(),
            'payable' => $order->payment_method === 'card' && $order->status === OrderStatus::AWAITING_PAYMENT,
        ]);
    }
}
