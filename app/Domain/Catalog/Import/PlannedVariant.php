<?php

namespace App\Domain\Catalog\Import;

final class PlannedVariant
{
    public int $row = 0;

    public string $sku = '';

    public ?string $barcode = null;

    public ?int $price = null;

    public ?int $compareAt = null;

    public ?int $cost = null;

    public ?int $weight = null;

    public ?int $stock = null;

    public ?int $threshold = null;

    public ?bool $track = null;

    public ?bool $backorder = null;

    /** @var list<int>|null null = coloana „Opțiuni” e goală */
    public ?array $optionIds = null;

    /** Varianta existentă (doar la actualizare). */
    public ?int $existingId = null;
}
