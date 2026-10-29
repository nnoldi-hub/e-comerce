<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\Actions\ProcessPaymentWebhookAction;
use App\Domain\Payments\Exceptions\InvalidPaymentNotificationException;
use App\Domain\Payments\Exceptions\UnsupportedGatewayException;
use App\Domain\Payments\PaymentGatewayManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            abort(403, 'Semnătură de webhook invalidă.');
        }

        $notification = $provider->parseWebhook($request);

        if ($notification === null) {
            return response()->json(['status' => 'ignored'], 202);
        }

        try {
            $process->execute($notification);
        } catch (InvalidPaymentNotificationException $exception) {
            report($exception);

            return response()->json([
                'status' => 'rejected',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['status' => 'ok']);
    }
}
