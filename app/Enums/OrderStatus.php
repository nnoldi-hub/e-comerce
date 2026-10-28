<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';
    case REFUNDED = 'refunded';
}
