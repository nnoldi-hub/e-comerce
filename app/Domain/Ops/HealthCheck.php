<?php

namespace App\Domain\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verificări de sănătate (runtime) și de pregătire pentru producție (configurare).
 * Fiecare verificare întoarce o stare: ok, warn sau fail. `fail` înseamnă că
 * aplicația nu funcționează corect acum; `warn`, că ceva cere atenție.
 */
class HealthCheck
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /** Cheia în care planificatorul scrie, în fiecare minut, momentul ultimei rulări. */
    public const HEARTBEAT_KEY = 'ops:scheduler-heartbeat';

    private const HEARTBEAT_MAX_AGE = 180;

    private const QUEUE_MAX_LAG = 300;

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    public function run(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->queue(),
            $this->scheduler(),
            $this->storage(),
            $this->disk(),
        ];
    }

    /**
     * Configurarea așteptată într-un mediu de producție.
     *
     * @return list<array{name: string, status: string, detail: string}>
     */
    public function production(): array
    {
        $mailer = (string) config('mail.default');
        $url = (string) config('app.url');
        $cardGateway = (string) config('shop.payments.methods.card');

        $checks = [
            $this->flag('env', config('app.env') === 'production', 'APP_ENV este production.', 'APP_ENV este „'.(string) config('app.env').'”, nu production.'),
            $this->flag('debug', config('app.debug') === false, 'APP_DEBUG este oprit.', 'APP_DEBUG este activ: erorile expun detalii interne.'),
            $this->flag('url', str_starts_with($url, 'https://'), 'APP_URL folosește HTTPS.', 'APP_URL nu începe cu https://.'),
            $this->flag('key', (string) config('app.key') !== '', 'APP_KEY este setat.', 'APP_KEY lipsește.'),
            $this->flag('mail', ! in_array($mailer, ['log', 'array'], true), "Mailer: {$mailer}.", "Mailer-ul „{$mailer}” nu trimite emailuri reale."),
            $this->flag('queue-driver', config('queue.default') !== 'sync', 'Coada nu rulează sincron.', 'QUEUE_CONNECTION=sync: joburile rulează în cererea utilizatorului.'),
            $this->flag('cache-driver', config('cache.default') !== 'array', 'Cache persistent.', 'CACHE_STORE=array: nu persistă între cereri.'),
            $this->flag('session', config('session.secure') === true && config('session.driver') !== 'array', 'Cookie de sesiune securizat.', 'SESSION_SECURE_COOKIE nu este true sau driverul de sesiune este array.'),
        ];

        if ($cardGateway === 'stripe') {
            $configured = (string) config('shop.payments.gateways.stripe.secret') !== ''
                && (string) config('shop.payments.gateways.stripe.webhook_secret') !== '';

            $checks[] = $this->flag('stripe', $configured, 'Stripe configurat.', 'Cardul folosește Stripe, dar STRIPE_SECRET / STRIPE_WEBHOOK_SECRET lipsesc.');
        } else {
            $checks[] = $this->result('stripe', self::WARN, "Cardul folosește gateway-ul „{$cardGateway}”, nu Stripe.");
        }

        $checks[] = $this->result('config-cache', app()->configurationIsCached() ? self::OK : self::WARN, app()->configurationIsCached() ? 'Config în cache.' : 'Rulează `php artisan optimize`.');
        $checks[] = $this->result('route-cache', app()->routesAreCached() ? self::OK : self::WARN, app()->routesAreCached() ? 'Rute în cache.' : 'Rulează `php artisan optimize`.');

        return $checks;
    }

    /**
     * @param  list<array{name: string, status: string, detail: string}>  $results
     */
    public static function worst(array $results): string
    {
        $statuses = array_column($results, 'status');

        return match (true) {
            in_array(self::FAIL, $statuses, true) => self::FAIL,
            in_array(self::WARN, $statuses, true) => self::WARN,
            default => self::OK,
        };
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function database(): array
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            return $this->result('database', self::FAIL, 'Baza de date nu răspunde ('.$e::class.').');
        }

        return $this->result('database', self::OK, 'Conexiune activă.');
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function cache(): array
    {
        try {
            Cache::put('ops:health-probe', 'ok', 10);
            $works = Cache::get('ops:health-probe') === 'ok';
        } catch (Throwable $e) {
            return $this->result('cache', self::FAIL, 'Cache indisponibil ('.$e::class.').');
        }

        return $works
            ? $this->result('cache', self::OK, 'Scriere și citire reușite.')
            : $this->result('cache', self::FAIL, 'Valoarea scrisă în cache nu poate fi citită.');
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return $this->result('queue', self::WARN, 'Coada rulează sincron: nu există worker.');
        }

        if ($connection !== 'database') {
            return $this->result('queue', self::OK, "Conexiune „{$connection}” (starea workerului nu se poate verifica de aici).");
        }

        try {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
            $oldest = DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->timestamp)
                ->min('available_at');
        } catch (Throwable $e) {
            return $this->result('queue', self::FAIL, 'Tabelele cozii nu pot fi citite ('.$e::class.').');
        }

        $lag = $oldest === null ? 0 : max(0, now()->timestamp - (int) $oldest);

        if ($lag > self::QUEUE_MAX_LAG) {
            return $this->result('queue', self::FAIL, "Cel mai vechi job așteaptă de {$lag} s: workerul pare oprit.");
        }

        if ($failed > 0) {
            return $this->result('queue', self::WARN, "{$failed} joburi eșuate în ultima oră.");
        }

        return $this->result('queue', self::OK, 'Fără întârzieri și fără joburi eșuate recent.');
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function scheduler(): array
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);

        if (! is_numeric($beat)) {
            return $this->result('scheduler', self::WARN, 'Nicio bătaie de inimă: cron-ul (`schedule:run`) nu a rulat încă.');
        }

        $age = now()->timestamp - (int) $beat;

        return $age > self::HEARTBEAT_MAX_AGE
            ? $this->result('scheduler', self::FAIL, "Ultima rulare a planificatorului a fost acum {$age} s: cron-ul pare oprit.")
            : $this->result('scheduler', self::OK, "Ultima rulare acum {$age} s.");
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function storage(): array
    {
        if (! is_writable(storage_path('logs'))) {
            return $this->result('storage', self::FAIL, 'storage/logs nu este inscriptibil.');
        }

        if (! file_exists(public_path('storage'))) {
            return $this->result('storage', self::WARN, 'Legătura public/storage lipsește (rulează `php artisan storage:link`).');
        }

        return $this->result('storage', self::OK, 'Inscriptibil, cu legătura public/storage.');
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || $total === false || $total <= 0) {
            return $this->result('disk', self::WARN, 'Spațiul liber nu poate fi citit.');
        }

        $percent = round($free / $total * 100, 1);

        return match (true) {
            $percent < 2 => $this->result('disk', self::FAIL, "Doar {$percent}% spațiu liber."),
            $percent < 5 => $this->result('disk', self::WARN, "Doar {$percent}% spațiu liber."),
            default => $this->result('disk', self::OK, "{$percent}% spațiu liber."),
        };
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function flag(string $name, bool $passed, string $ok, string $problem): array
    {
        return $passed ? $this->result($name, self::OK, $ok) : $this->result($name, self::FAIL, $problem);
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function result(string $name, string $status, string $detail): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }
}
