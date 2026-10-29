<?php

namespace App\Domain\Payments\Exceptions;

/**
 * Comanda nu mai poate fi plătită (anulată, deja plătită sau într-un status
 * care nu așteaptă bani).
 */
class OrderNotPayableException extends PaymentException {}
