<?php

namespace Tests\Feature\Admin;

use App\Domain\Reporting\OrdersCsvExport;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

class OrdersCsvExportTest extends ReportingTestCase
{
    private function csv(string $from = '2026-10-01', string $to = '2026-10-30'): string
    {
        $handle = fopen('php://memory', 'w+');
        app(OrdersCsvExport::class)->write($handle, CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay());
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    public function test_the_export_has_a_bom_a_header_and_one_line_per_order_in_the_period(): void
    {
        $in = $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID, quantity: 2);
        $this->orderAt('2026-09-05 10:00:00', OrderStatus::PAID);

        $content = $this->csv();
        $lines = array_values(array_filter(explode("\n", trim($content))));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('numar,plasata_la,status', $lines[0]);
        $this->assertStringContainsString($in->number, $lines[1]);
        $this->assertStringContainsString('2026-10-05 10:00:00', $lines[1]);
        $this->assertStringContainsString(number_format($in->grand_total->amount() / 100, 2, '.', ''), $lines[1]);
    }

    public function test_the_export_contains_no_personal_data(): void
    {
        $order = $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID);

        $content = $this->csv();

        $this->assertStringNotContainsString($order->email, $content);
        $this->assertStringNotContainsString('@', $content);
    }

    public function test_formula_like_cells_are_neutralised(): void
    {
        $order = $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID);
        $order->update(['coupon_code' => '=HYPERLINK("http://x")']);

        $this->assertStringContainsString("'=HYPERLINK", $this->csv());
    }

    public function test_the_action_downloads_a_file_and_is_audited(): void
    {
        $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID);
        $staff = $this->staff('Suport Clienți');
        $this->actingAs($staff);

        Livewire::test(ListOrders::class)
            ->callAction('exportCsv', ['from' => '2026-10-01', 'until' => '2026-10-30'])
            ->assertFileDownloaded('comenzi-2026-10-01_2026-10-30.csv');

        $log = Activity::query()->where('event', 'export')->sole();
        $this->assertSame($staff->id, $log->causer_id);
    }

    public function test_a_period_longer_than_a_year_is_rejected(): void
    {
        $this->actingAs($this->staff('Suport Clienți'));

        Livewire::test(ListOrders::class)
            ->callAction('exportCsv', ['from' => '2024-01-01', 'until' => '2026-10-30'])
            ->assertHasActionErrors(['until']);
    }

    public function test_roles_without_order_access_cannot_use_the_export(): void
    {
        $this->actingAs($this->staff('Manager Inventar'));

        $this->get('/admin/orders')->assertForbidden();
    }
}
