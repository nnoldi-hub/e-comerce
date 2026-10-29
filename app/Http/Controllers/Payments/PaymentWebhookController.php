<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\Actions\ProcessPaymentWebhookAction;
use App\Domain\Payments\Exceptions\PaymentNotFoundException;
use App\Domain\Payments\Exceptions\UnsupportedGatewayException;
use App\Domain\Payments\PaymentGatewayManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $gateway,
        PaymentGatewayManager $gateways,
        ProcessPaymentWebhookAction $process,
    ): JsonResponse {
        try {
            $provider = $gateways->gateway($gateway);
        } catch (UnsupportedGatewayException) {
            abort(404);
        }

        if (! $provider->verifyWebhook($request)) {
            Log::warning('Webhook de plată respins: semnătură invalidă.', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'invalid_signature'], 403);
        }

        $notification = $provider->parseWebhook($request);

        if ($notification === null) {
            return response()->json(['status' => 'ignored'], 202);
        }

        try {
            $outcome = $process->execute($notification);
        } catch (PaymentNotFoundException $exception) {
            // Probabil o cursă: providerul reîncearcă în următoarele minute.
            return response()->json(['status' => 'retry', 'message' => $exception->getMessage()], 409);
        }

        if ($outcome === ProcessPaymentWebhookAction::REJECTED) {
            // Eroare permanentă, deja înregistrată: 202 oprește retrimiterile.
            return response()->json(['status' => 'rejected'], 202);
        }

        return response()->json(['status' => 'ok']);
    }
}
