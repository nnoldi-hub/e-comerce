<?php

use App\Domain\Payments\Gateways\CashOnDeliveryGateway;
use App\Domain\Payments\Gateways\ManualGateway;
use App\Domain\Payments\Gateways\StripeGateway;

return [

    /*
    | Cât timp rămâne rezervat stocul unei comenzi neplătite, în funcție de
    | metoda de plată. După expirare, rezervarea este eliberată automat.
    |
    | Pentru card, rezervarea (45 min) este mai lungă decât sesiunea Stripe
    | Checkout (31 min, vezi StripeGateway): o plată nu poate sosi după ce
    | comanda a fost anulată pentru expirare.
    */
    'reservation_minutes' => [
        'card' => 45,
        'cod' => 1440,
    ],

    /*
    | Gateway-uri de plată. `methods` leagă metoda aleasă în checkout de un
    | gateway configurat mai jos. Implicit cardul folosește gateway-ul manual
    | (fără redirect), ca developmentul să nu depindă de credențiale Stripe.
    */
    'payments' => [
        'default' => 'manual',

        'methods' => [
            'card' => env('PAYMENT_CARD_GATEWAY', 'manual'),
            'cod' => 'cod',
        ],

        'gateways' => [
            'manual' => [
                'driver' => ManualGateway::class,
            ],
            'cod' => [
                'driver' => CashOnDeliveryGateway::class,
            ],
            'stripe' => [
                'driver' => StripeGateway::class,
                'secret' => env('STRIPE_SECRET', ''),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
                'tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
            ],
        ],
    ],

];
