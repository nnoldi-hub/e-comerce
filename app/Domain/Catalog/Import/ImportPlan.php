<?php

namespace App\Domain\Catalog\Import;

final class ImportPlan
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    /** @var list<PlannedProduct> */
    public array $products = [];

    /**
     * Probleme care nu țin de un produs anume (antet, coloane lipsă).
     *
     * @var list<ImportIssue>
     */
    public array $general = [];

    /** Eroare fatală: nimic nu se poate importa din acest fișier. */
    public ?string $fatal = null;

    public function __construct(public readonly string $mode) {}

    /** @return list<PlannedProduct> */
    public function valid(): array
    {
        return array_values(array_filter($this->products, fn (PlannedProduct $p): bool => ! $p->hasErrors()));
    }

    /** @return list<PlannedProduct> */
    public function invalid(): array
    {
        return array_values(array_filter($this->products, fn (PlannedProduct $p): bool => $p->hasErrors()));
    }

    /** @return list<ImportIssue> */
    public function issues(): array
    {
        $all = $this->general;

        foreach ($this->products as $product) {
            array_push($all, ...$product->issues);
        }

        usort($all, fn (ImportIssue $a, ImportIssue $b): int => $a->row <=> $b->row);

        return $all;
    }
}
