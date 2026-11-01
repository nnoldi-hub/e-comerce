<?php

namespace App\Domain\Catalog\Import;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Descarcă imaginile unui produs nou din URL-uri și le atașează în Media Library.
 * O imagine care nu se poate descărca devine avertisment; produsul rămâne creat.
 *
 * Doar http/https, doar adrese publice (nu 127.0.0.1, 192.168.x.x etc.), cu limită
 * de mărime și verificarea conținutului: URL-urile vin dintr-un fișier încărcat.
 */
class ImageFetcher
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @param  list<string>  $gallery
     * @return list<string> avertismente
     */
    public function attach(Product $product, ?string $thumbnail, array $gallery): array
    {
        $warnings = [];

        $jobs = [];

        if ($thumbnail !== null) {
            $jobs[] = ['thumbnail', $thumbnail];
        }

        foreach ($gallery as $url) {
            $jobs[] = ['gallery', $url];
        }

        foreach ($jobs as [$collection, $url]) {
            try {
                [$path, $extension] = $this->download($url);

                try {
                    $product->addMedia($path)
                        ->usingFileName(uniqid('import-', true).'.'.$extension)
                        ->toMediaCollection($collection);
                } finally {
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            } catch (Throwable $e) {
                $warnings[] = 'Imaginea '.$url.' nu a fost adăugată: '.$e->getMessage();
            }
        }

        return $warnings;
    }

    /**
     * @return array{0: string, 1: string} calea fișierului temporar și extensia
     */
    private function download(string $url): array
    {
        $this->assertPublic($url);

        $verify = (bool) config('catalog.import.verify_hosts', true);

        $response = Http::timeout(10)
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri) use ($verify): void {
                        if ($verify) {
                            $this->assertPublic((string) $uri);
                        }
                    },
                ],
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('serverul a răspuns cu eroare '.$response->status());
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('fișierul este gol sau depășește 5 MB');
        }

        $info = @getimagesizefromstring($body);
        $mime = is_array($info) ? $info['mime'] : '';

        if (! in_array($mime, Product::IMAGE_MIME_TYPES, true)) {
            throw new RuntimeException('nu este o imagine JPG, PNG sau WebP');
        }

        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $path = tempnam(sys_get_temp_dir(), 'imp');

        if ($path === false || file_put_contents($path, $body) === false) {
            throw new RuntimeException('nu s-a putut salva temporar');
        }

        return [$path, $extension];
    }

    private function assertPublic(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('adresa nu este http(s)');
        }

        if (! config('catalog.import.verify_hosts', true)) {
            return;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            throw new RuntimeException('adresa nu poate fi rezolvată');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('adresa nu este publică');
            }
        }
    }
}
