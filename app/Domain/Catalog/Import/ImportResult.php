<?php

namespace App\Domain\Catalog\Import;

final class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    /** @var list<ImportIssue> */
    public array $issues = [];

    public int $failed = 0;
}
