<?php

namespace App\Http\Controllers\Checkout;

use App\Domain\Payments\Actions\StartPaymentAction;
use App\Domain\Payments\Exceptions\OrderNotPayableException;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

/**
 * „Plătește acum": repornește plata unei comenzi. Ruta este semnată (link
 * valabil 24 h, generat pe pagina de succes), deci nu cere autentificare
 * pentru clienții fără cont.
 */
class PayOrderController extends Controller
{
    public function __invoke(Order $order, StartPaymentAction $payments): RedirectResponse
    {
        try {
            $payment = $payments->execute($order);
        } catch (OrderNotPayableException) {
            return back()->with('payment_error', 'Comanda nu mai poate fi plătită.');
        } catch (PaymentException $exception) {
            report($exception);

            return back()->with('payment_error', 'Nu am putut porni plata. Te rugăm să încerci din nou.');
        }

        $redirect = $payment->payload['redirect_url'] ?? null;

        if (is_string($redirect) && $redirect !== '') {
            return redirect()->away($redirect);
        }

        return back()->with('payment_notice', 'Plata a fost înregistrată.');
    }
}
