<?php

namespace App\Listeners;

use App\Domain\Inventory\Events\LowStockAlertRaised;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Rulează imediat (după commit) doar ca să împrăștie notificările; fiecare
 * notificare are propria sarcină în coadă, cu reîncercări independente.
 * O eroare aici nu trebuie să strice operația de stoc care a declanșat-o.
 */
class NotifyLowStock
{
    public function handle(LowStockAlertRaised $event): void
    {
        try {
            $alert = LowStockAlert::query()->find($event->alertId);

            if ($alert === null || $alert->resolved_at !== null) {
                return;
            }

            $variant = ProductVariant::query()->find($alert->product_variant_id);
            $product = $variant === null ? null : Product::query()->find($variant->product_id);

            if ($variant === null || $product === null) {
                return;
            }

            $recipients = User::query()
                ->permission('inventory.alerts.receive')
                ->where('is_active', true)
                ->get();

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new LowStockNotification(
                variantId: $variant->id,
                productId: $product->id,
                productName: $product->name,
                sku: $variant->sku,
                stock: $alert->stock_on_hand,
                threshold: $alert->low_stock_threshold,
                outOfStock: $event->outOfStock,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
