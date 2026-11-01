<?php

namespace App\Domain\Catalog\Import;

final class PlannedProduct
{
    public int $row = 0;

    public string $name = '';

    public ?string $description = null;

    public ?int $brandId = null;

    /** @var list<int>|null null = nu se modifică */
    public ?array $categoryIds = null;

    public ?string $status = null;

    public ?string $metaTitle = null;

    public ?string $metaDescription = null;

    public ?string $image = null;

    /** @var list<string> */
    public array $gallery = [];

    public string $type = 'simple';

    /** Produsul existent (doar la actualizare). */
    public ?int $existingId = null;

    /** @var list<PlannedVariant> */
    public array $variants = [];

    /** @var list<ImportIssue> */
    public array $issues = [];

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->level === ImportIssue::ERROR) {
                return true;
            }
        }

        return false;
    }

    public function firstSku(): string
    {
        return isset($this->variants[0]) ? $this->variants[0]->sku : '';
    }

    public function label(): string
    {
        return $this->name !== '' ? $this->name : ($this->firstSku() !== '' ? $this->firstSku() : '—');
    }
}
