<?php

/*
 * Proces separat folosit de OrderConcurrencyTest: plasează o comandă și
 * afișează rezultatul ca JSON. Pornește la momentul $startAt, ca procesele
 * să concureze efectiv pentru aceleași rânduri.
 *
 * Utilizare: php place_order_worker.php <cartId> <idempotencyKey> <shippingMethodId> <startAt>
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $cartId, $idempotencyKey, $shippingMethodId, $startAt] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

$address = [
    'first_name' => 'Worker',
    'last_name' => 'Test',
    'phone' => '0712345678',
    'address_line1' => 'Str. Test 1',
    'city' => 'București',
    'postal_code' => '010101',
    'country' => 'RO',
];

try {
    $cart = App\Models\Cart::query()->findOrFail((int) $cartId);

    $order = $app->make(App\Domain\Checkout\Actions\PlaceOrderAction::class)->execute(
        $cart,
        new App\Domain\Checkout\DTOs\PlaceOrderData(
            email: 'worker@example.com',
            billingAddress: $address,
            shippingAddress: $address,
            shippingMethodId: (int) $shippingMethodId,
            paymentMethod: 'card',
            idempotencyKey: $idempotencyKey,
        )
    );

    echo json_encode(['ok' => true, 'number' => $order->number])."\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'class' => class_basename($e), 'message' => $e->getMessage()])."\n";
}
