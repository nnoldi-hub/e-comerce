<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Primește rapoartele de încălcare CSP trimise de browsere. Endpoint public,
 * deci defensiv: corp limitat, conținut tratat ca date nesigure (doar câmpuri
 * scalare scurte intră în log) și întotdeauna 204.
 */
class CspReportController extends Controller
{
    private const MAX_BYTES = 8192;

    private const FIELDS = [
        'document-uri', 'violated-directive', 'effective-directive',
        'blocked-uri', 'source-file', 'line-number', 'disposition',
    ];

    public function __invoke(Request $request): Response
    {
        $body = (string) $request->getContent();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return response()->noContent();
        }

        $decoded = json_decode($body, true);
        $report = is_array($decoded) ? ($decoded['csp-report'] ?? null) : null;

        if (! is_array($report)) {
            return response()->noContent();
        }

        $context = [];

        foreach (self::FIELDS as $field) {
            $value = $report[$field] ?? null;

            if (is_scalar($value)) {
                $context[$field] = mb_substr((string) $value, 0, 300);
            }
        }

        Log::warning('Raport CSP', $context);

        return response()->noContent();
    }
}
