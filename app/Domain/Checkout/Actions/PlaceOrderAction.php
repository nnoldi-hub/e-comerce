<?php

namespace App\Domain\Checkout\Actions;

use App\Domain\Checkout\DTOs\PlaceOrderData;
use App\Domain\Checkout\Events\OrderPlaced;
use App\Domain\Checkout\Exceptions\EmptyCartException;
use App\Domain\Checkout\Exceptions\IdempotencyConflictException;
use App\Domain\Checkout\Exceptions\InsufficientStockException;
use App\Domain\Checkout\Exceptions\InvalidCouponException;
use App\Domain\Checkout\Exceptions\ProductUnavailableException;
use App\Domain\Checkout\Exceptions\ShippingUnavailableException;
use App\Domain\Pricing\DTOs\PricingInput;
use App\Domain\Pricing\PriceCalculator;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Transformă un coș într-o comandă, atomic și fără suprăvânzare.
 *
 * Reguli de concurență (InnoDB, REPEATABLE READ):
 *  - Toate blocările se iau ÎNAINTE de prima citire simplă din tranzacție
 *    (citirile simple creează snapshot-ul), în ordine fixă: liniile coșului,
 *    variantele (după id), cuponul. Astfel citirile ulterioare văd tot ce
 *    au confirmat tranzacțiile concurente, iar ordinea fixă evită deadlock-urile.
 *  - Idempotența NU se verifică cu FOR UPDATE: un lock pe o cheie unică
 *    inexistentă ia gap lock și provoacă deadlock între comenzi concurente.
 *    Cheia unică din baza de date rămâne plasa de siguranță.
 */
class PlaceOrderAction
{
    public function __construct(private readonly PriceCalculator $prices) {}

    public function execute(Cart $cart, PlaceOrderData $data): Order
    {
        $existing = $this->existingOrder($data);

        if ($existing !== null) {
            return $existing;
        }

        try {
            [$order, $created] = DB::transaction(fn () => $this->place($cart, $data), 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Două cereri cu aceeași cheie au ajuns simultan la INSERT.
            $existing = $this->existingOrder($data);

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }

        if ($created) {
            OrderPlaced::dispatch($order);
        }

        return $order;
    }

    /**
     * @return array{0: Order, 1: bool} comanda și dacă a fost creată acum
     */
    private function place(Cart $cart, PlaceOrderData $data): array
    {
        // 1) Blocări (doar citiri cu blocare până la final de lock-uri).
        $items = CartItem::query()
            ->where('cart_id', $cart->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($items->isEmpty()) {
            // Cererea a așteptat o alta pe același coș, care a terminat între timp.
            $existing = $this->existingOrder($data);

            if ($existing !== null) {
                return [$existing, false];
            }

            throw new EmptyCartException;
        }

        $variantIds = $items->pluck('product_variant_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $couponCode = trim((string) $data->couponCode);

        if ($couponCode !== '') {
            // Serializează folosirea aceluiași cupon; rezultatul se ignoră aici.
            Coupon::query()->where('code', $couponCode)->lockForUpdate()->first();
        }

        // 2) De aici, citirile simple văd datele confirmate de ceilalți.
        $existing = $this->existingOrder($data);

        if ($existing !== null) {
            return [$existing, false];
        }

        $products = Product::query()
            ->active()
            ->whereIn('id', $variants->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $unavailable = [];
        $shortages = [];

        foreach ($items as $item) {
            $variantId = (int) $item->product_variant_id;
            $variant = $variants->get($variantId);

            if ($variant === null || ! $products->has($variant->product_id)) {
                $unavailable[] = $variantId;

                continue;
            }

            if ($variant->track_inventory && ! $variant->allow_backorder) {
                $available = $variant->stock_on_hand - $variant->stock_reserved;

                if ($item->quantity > $available) {
                    $shortages[] = [
                        'variant_id' => $variantId,
                        'sku' => $variant->sku,
                        'requested' => $item->quantity,
                        'available' => max(0, $available),
                    ];
                }
            }

            $variant->setRelation('product', $products->get($variant->product_id));
            $item->setRelation('variant', $variant);
        }

        if ($unavailable !== []) {
            throw new ProductUnavailableException($unavailable);
        }

        if ($shortages !== []) {
            throw new InsufficientStockException($shortages);
        }

        // 3) Prețuri calculate pe server, din variantele blocate.
        $cart->setRelation('items', $items);

        $totals = $this->prices->calculate($cart, new PricingInput(
            couponCode: $couponCode !== '' ? $couponCode : null,
            shippingMethodId: $data->shippingMethodId,
            country: $data->country(),
            userId: $data->userId,
        ));

        if ($totals->couponError !== null) {
            throw new InvalidCouponException($totals->couponError);
        }

        if ($totals->shippingError !== null || $totals->shippingMethodId === null) {
            throw new ShippingUnavailableException($totals->shippingError ?? 'required');
        }

        // 4) Scrieri.
        $order = Order::create([
            'number' => 'TMP-'.Str::ulid(),
            'user_id' => $data->userId,
            'email' => $data->email,
            'status' => $data->paymentMethod === 'card' ? OrderStatus::AWAITING_PAYMENT : OrderStatus::PENDING,
            'payment_status' => PaymentStatus::PENDING,
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => $totals->subtotal,
            'discount_total' => $totals->discount,
            'shipping_total' => $totals->shipping,
            'tax_total' => $totals->tax,
            'grand_total' => $totals->grandTotal,
            'currency' => $totals->currency,
            'billing_address' => $data->billingAddress,
            'shipping_address' => $data->shippingAddress,
            'coupon_code' => $totals->couponCode,
            'idempotency_key' => $data->idempotencyKey,
            'placed_at' => now(),
            'customer_note' => $data->customerNote,
        ]);

        // Număr derivat din id (unic prin construcție, fără condiții de cursă).
        $order->update(['number' => sprintf('%s-%06d', now()->format('Y'), $order->id)]);

        $expiresAt = now()->addMinutes((int) config("shop.reservation_minutes.{$data->paymentMethod}", 30));

        foreach ($totals->lines as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $line->productVariantId,
                'name' => $line->name,
                'sku' => $line->sku,
                'unit_price' => $line->unitPrice,
                'tax_rate_bps' => $totals->taxRateBps,
                'quantity' => $line->quantity,
                'line_total' => $line->lineTotal,
            ]);

            $variant = $variants->get($line->productVariantId);

            if (! $variant->track_inventory) {
                continue;
            }

            $variant->increment('stock_reserved', $line->quantity);

            StockReservation::create([
                'product_variant_id' => $variant->id,
                'order_id' => $order->id,
                'quantity' => $line->quantity,
                'expires_at' => $expiresAt,
            ]);
        }

        if ($totals->couponId !== null) {
            CouponUsage::create([
                'coupon_id' => $totals->couponId,
                'user_id' => $data->userId,
                'order_id' => $order->id,
            ]);
        }

        CartItem::query()->where('cart_id', $cart->id)->delete();

        return [$order->load('items'), true];
    }

    private function existingOrder(PlaceOrderData $data): ?Order
    {
        $order = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();

        if ($order === null) {
            return null;
        }

        if ($order->email !== $data->email) {
            throw new IdempotencyConflictException;
        }

        return $order->load('items');
    }
}
