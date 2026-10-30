<?php

namespace App\Console\Commands;

use App\Domain\Ops\HealthCheck;
use Illuminate\Console\Command;

class OpsCheck extends Command
{
    protected $signature = 'ops:check {--production : Verifică și configurarea pentru producție}';

    protected $description = 'Verifică sănătatea aplicației (baza de date, cache, coadă, cron, disc); ieșire non-zero dacă ceva e în eșec';

    public function handle(HealthCheck $health): int
    {
        $results = $health->run();

        if ($this->option('production')) {
            $results = [...$results, ...$health->production()];
        }

        $this->table(
            ['Verificare', 'Stare', 'Detalii'],
            array_map(fn (array $r): array => [$r['name'], strtoupper($r['status']), $r['detail']], $results),
        );

        $worst = HealthCheck::worst($results);
        $this->line('Rezultat: '.strtoupper($worst));

        return $worst === HealthCheck::FAIL ? self::FAILURE : self::SUCCESS;
    }
}
