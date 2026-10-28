<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class PricingSeeder extends Seeder
{
    public function run(): void
    {
        // Cote TVA România (puncte de bază): standard 21%, redus 11%.
        TaxRate::updateOrCreate(
            ['name' => 'TVA standard RO', 'country' => 'RO'],
            ['rate_bps' => 2100, 'is_inclusive' => true, 'is_default' => true, 'is_active' => true],
        );

        TaxRate::updateOrCreate(
            ['name' => 'TVA redus RO', 'country' => 'RO'],
            ['rate_bps' => 1100, 'is_inclusive' => true, 'is_default' => false, 'is_active' => true],
        );

        $zone = ShippingZone::updateOrCreate(
            ['name' => 'România'],
            ['countries' => ['RO'], 'is_active' => true],
        );

        // Curier: 19,99 RON, gratuit de la 200 RON (sume în bani).
        $courier = ShippingMethod::updateOrCreate(
            ['shipping_zone_id' => $zone->id, 'name' => 'Curier'],
            ['is_active' => true, 'position' => 1],
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $courier->id, 'min_subtotal' => 0],
            ['max_subtotal' => 19999, 'price' => 1999],
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $courier->id, 'min_subtotal' => 20000],
            ['max_subtotal' => null, 'price' => 0],
        );

        $pickup = ShippingMethod::updateOrCreate(
            ['shipping_zone_id' => $zone->id, 'name' => 'Ridicare personală'],
            ['is_active' => true, 'position' => 2],
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $pickup->id, 'min_subtotal' => 0],
            ['max_subtotal' => null, 'price' => 0],
        );

        // Cupon de test: 10% (1000 puncte de bază).
        Coupon::updateOrCreate(
            ['code' => 'BUN10'],
            ['type' => 'percent', 'value' => 1000, 'is_active' => true],
        );
    }
}
