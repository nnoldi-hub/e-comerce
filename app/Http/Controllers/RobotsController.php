<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /cont',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /webhooks',
            'Disallow: /health',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        // În afara producției nimic nu trebuie indexat (staging, demo).
        if (! app()->isProduction()) {
            $lines = ['User-agent: *', 'Disallow: /'];
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
