<?php

namespace App\Domain\Payments\Exceptions;

/**
 * Webhook-ul face referire la o plată pe care nu o (mai) găsim. Poate fi o
 * cursă: providerul trimite evenimentul înainte ca noi să fi salvat
 * referința. De aceea este reîncercat o vreme, apoi abandonat.
 */
class PaymentNotFoundException extends InvalidPaymentNotificationException {}
