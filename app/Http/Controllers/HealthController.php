<?php

namespace App\Http\Controllers;

use App\Domain\Ops\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /health, pentru monitorizare. Necesită `Authorization: Bearer <HEALTH_TOKEN>`;
 * fără token configurat sau cu token greșit răspunde 404 (nu dezvăluie că există).
 * Pentru un simplu „e pornită?” folosește `/up` al framework-ului.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, HealthCheck $health): JsonResponse
    {
        $token = (string) config('security.health_token');

        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            abort(404);
        }

        $results = $health->run();
        $status = HealthCheck::worst($results);

        return response()->json(
            ['status' => $status, 'checks' => $results],
            $status === HealthCheck::FAIL ? 503 : 200,
        );
    }
}
