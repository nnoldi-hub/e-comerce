<?php

namespace App\Domain\Catalog\Import;

final class ImportIssue
{
    public const ERROR = 'eroare';

    public const WARNING = 'avertisment';

    public function __construct(
        /** Numărul rândului din fișier (antetul este rândul 1). */
        public readonly int $row,
        public readonly string $sku,
        public readonly string $level,
        public readonly string $message,
    ) {}
}
