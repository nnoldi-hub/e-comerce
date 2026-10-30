<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::SHIPPED => 'Expediată',
            self::DELIVERED => 'Livrată',
        };
    }
}
