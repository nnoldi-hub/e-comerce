<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\OrderResource;
use Tests\TestCase;

class OrderAddressFormatTest extends TestCase
{
    public function test_a_shipping_address_is_laid_out_like_a_courier_label(): void
    {
        $lines = OrderResource::addressLines([
            'first_name' => 'Ion', 'last_name' => 'Popescu', 'phone' => '0712345678',
            'address_line1' => 'Str. Exemplu 1', 'address_line2' => 'Bl. 2, ap. 5',
            'city' => 'Brănești', 'county' => 'Ilfov', 'postal_code' => '077030', 'country' => 'RO',
        ]);

        $this->assertSame([
            'Ion Popescu',
            'Str. Exemplu 1',
            'Bl. 2, ap. 5',
            '077030 Brănești, jud. Ilfov',
            'România',
            'Tel: 0712345678',
        ], $lines);
    }

    public function test_company_details_are_included_and_empty_fields_are_skipped(): void
    {
        $lines = OrderResource::addressLines([
            'first_name' => 'Ana', 'last_name' => 'Ionescu', 'company_name' => 'Firma SRL', 'cui' => 'RO1234565',
            'reg_com' => '', 'address_line1' => 'Str. A 1', 'address_line2' => null,
            'city' => 'București', 'county' => null, 'postal_code' => '010101', 'country' => 'RO', 'phone' => '',
        ]);

        $this->assertContains('Firma SRL', $lines);
        $this->assertContains('CUI: RO1234565', $lines);
        $this->assertContains('010101 București', $lines);
        $this->assertNotContains('', $lines);
        $this->assertFalse(collect($lines)->contains(fn (string $l): bool => str_starts_with($l, 'Reg. Com.')));
    }

    public function test_a_missing_address_gives_no_lines(): void
    {
        $this->assertSame([], OrderResource::addressLines(null));
        $this->assertSame([], OrderResource::addressLines([]));
    }
}
