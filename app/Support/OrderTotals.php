<?php

namespace App\Support;

use App\Models\Order;

/**
 * Rândurile de totaluri ale unei comenzi (pagina de succes, emailuri), calculate
 * într-un singur loc. TVA-ul este „inclus” când subtotal − reducere + transport
 * dă exact totalul; altfel este o linie care se adaugă.
 */
final class OrderTotals
{
    /**
     * @return list<array{label: string, amount: int, kind: string}> kind: normal | discount | free | info | total
     */
    public static function rows(Order $order): array
    {
        $subtotal = $order->subtotal->amount();
        $discount = $order->discount_total->amount();
        $shipping = $order->shipping_total->amount();
        $tax = $order->tax_total->amount();
        $grand = $order->grand_total->amount();

        $rows = [['label' => 'Subtotal', 'amount' => $subtotal, 'kind' => 'normal']];

        if ($discount > 0) {
            $label = $order->coupon_code !== null && $order->coupon_code !== '' ? 'Reducere ('.$order->coupon_code.')' : 'Reducere';
            $rows[] = ['label' => $label, 'amount' => $discount, 'kind' => 'discount'];
        }

        $rows[] = ['label' => 'Transport', 'amount' => $shipping, 'kind' => $shipping === 0 ? 'free' : 'normal'];

        if ($tax > 0) {
            $inclusive = $subtotal - $discount + $shipping === $grand;
            $rows[] = $inclusive
                ? ['label' => 'din care TVA', 'amount' => $tax, 'kind' => 'info']
                : ['label' => 'TVA', 'amount' => $tax, 'kind' => 'normal'];
        }

        $rows[] = ['label' => 'Total', 'amount' => $grand, 'kind' => 'total'];

        return $rows;
    }
}
