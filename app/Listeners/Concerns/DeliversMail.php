<?php

namespace App\Listeners\Concerns;

use Throwable;

/**
 * Trimiterea emailului nu trebuie să strice comanda.
 *
 *  - Pe o coadă reală, excepția se propagă: job-ul este reîncercat cu backoff.
 *  - Cu driverul `sync` (dezvoltare, teste) nu există reîncercare, deci eroarea
 *    este doar raportată, ca să nu apară un 500 după ce comanda a fost creată.
 */
trait DeliversMail
{
    /**
     * @param  callable(): void  $send
     */
    protected function deliver(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            if ($this->runsSynchronously()) {
                report($exception);

                return;
            }

            throw $exception;
        }
    }

    private function runsSynchronously(): bool
    {
        return $this->job === null || $this->job->getConnectionName() === 'sync';
    }
}
