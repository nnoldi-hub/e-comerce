<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case RESERVE = 'reserve';
    case RELEASE = 'release';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
    case DAMAGE = 'damage';
}
