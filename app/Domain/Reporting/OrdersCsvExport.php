<?php

namespace App\Domain\Reporting;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Export CSV al comenzilor plasate într-o perioadă. Fără date personale
 * (nici email, nici adrese): doar identificatori, stări și sume.
 * UTF-8 cu BOM (Excel), sume cu punct zecimal, coduri de status stabile.
 */
class OrdersCsvExport
{
    public const HEADER = [
        'numar', 'plasata_la', 'status', 'plata', 'metoda_plata',
        'subtotal', 'reducere', 'transport', 'tva', 'total', 'moneda', 'cupon',
    ];

    /**
     * @param  resource  $handle
     * @return int numărul de comenzi scrise
     */
    public function write($handle, CarbonInterface $from, CarbonInterface $to): int
    {
        fwrite($handle, "\xEF\xBB\xBF");
        $this->put($handle, self::HEADER);

        $count = 0;

        Order::query()
            ->whereBetween('placed_at', [$from, $to])
            ->chunkById(500, function (Collection $orders) use ($handle, &$count): void {
                foreach ($orders as $order) {
                    $this->put($handle, $this->row($order));
                    $count++;
                }
            });

        return $count;
    }

    /** @return list<string> */
    private function row(Order $order): array
    {
        return [
            $order->number,
            $order->placed_at?->format('Y-m-d H:i:s') ?? '',
            $order->status->value,
            $order->payment_status->value,
            (string) $order->payment_method,
            $this->amount($order->subtotal->amount()),
            $this->amount($order->discount_total->amount()),
            $this->amount($order->shipping_total->amount()),
            $this->amount($order->tax_total->amount()),
            $this->amount($order->grand_total->amount()),
            $order->currency,
            (string) $order->coupon_code,
        ];
    }

    private function amount(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $row
     */
    private function put($handle, array $row): void
    {
        // Celulele care încep cu = + - @ ar fi interpretate ca formule în Excel.
        $safe = array_map(
            fn (string $cell): string => preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell,
            $row,
        );

        fputcsv($handle, $safe, ',', '"', '\\');
    }
}
