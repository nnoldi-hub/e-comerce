<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\ProductAggregates;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Pricing\PricingTestCase;

class SeoAndHardeningTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('sitemap.xml');
    }

    private function product(string $name, array $attributes = []): Product
    {
        $variant = $this->makeVariant(5000, product: array_merge(['name' => $name], $attributes));
        app(ProductAggregates::class)->refresh($variant->product_id);

        return Product::query()->findOrFail($variant->product_id);
    }

    public function test_storefront_pages_get_a_report_only_csp(): void
    {
        $response = $this->get('/');

        $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('report-uri /webhooks/csp-report', $csp);
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_enforce_mode_uses_the_blocking_header_and_off_removes_it(): void
    {
        config(['security.csp.mode' => 'enforce']);
        $this->get('/')->assertHeader('Content-Security-Policy', SecurityHeaders::policy());

        config(['security.csp.mode' => 'off']);
        $response = $this->get('/');
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_the_admin_panel_and_non_html_responses_get_no_csp(): void
    {
        $this->assertFalse($this->get('/admin/login')->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($this->get('/sitemap.xml')->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($this->get('/up')->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_csp_reports_are_logged_with_only_known_short_fields(): void
    {
        Log::spy();

        $this->call('POST', '/webhooks/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], (string) json_encode([
            'csp-report' => [
                'document-uri' => 'https://shop.test/',
                'violated-directive' => 'script-src',
                'blocked-uri' => str_repeat('x', 1000),
                'evil' => 'ignored',
            ],
        ]))->assertNoContent();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Raport CSP'
                && $context['violated-directive'] === 'script-src'
                && strlen($context['blocked-uri']) === 300
                && ! array_key_exists('evil', $context);
        });
    }

    public function test_malformed_or_oversized_csp_reports_are_ignored_quietly(): void
    {
        Log::spy();

        $this->call('POST', '/webhooks/csp-report', [], [], [], [], 'not json')->assertNoContent();
        $this->call('POST', '/webhooks/csp-report', [], [], [], [], str_repeat('a', 20000))->assertNoContent();
        $this->call('POST', '/webhooks/csp-report', [], [], [], [], '')->assertNoContent();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_robots_blocks_private_areas_and_points_to_the_sitemap_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /cont')
            ->assertSee('Disallow: /checkout')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    }

    public function test_the_sitemap_lists_public_pages_only(): void
    {
        $visible = $this->product('Vizibil');
        $draft = $this->product('Ciorna', ['status' => 'draft']);
        Category::create(['name' => 'Electronice', 'is_active' => true, 'position' => 0]);
        Category::create(['name' => 'Ascunsa', 'is_active' => false, 'position' => 0]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString(url('/'), $xml);
        $this->assertStringContainsString(route('catalog.product', $visible), $xml);
        $this->assertStringContainsString(route('catalog.category', ['path' => 'electronice']), $xml);
        $this->assertStringNotContainsString(route('catalog.product', $draft), $xml);
        $this->assertStringNotContainsString('/categorie/ascunsa', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'Sitemap-ul trebuie să fie XML valid.');
    }

    public function test_the_home_page_carries_website_structured_data(): void
    {
        $this->get('/')
            ->assertSee('application/ld+json', false)
            ->assertSee('SearchAction', false);
    }

    public function test_unknown_pages_render_the_branded_404(): void
    {
        $this->get('/nu-exista-pagina-asta')
            ->assertNotFound()
            ->assertSee('Pagina nu a fost găsită')
            ->assertSee('Pagina principală');
    }

    public function test_server_error_pages_do_not_depend_on_the_layout(): void
    {
        foreach (['errors.500' => 'Eroare de server', 'errors.503' => 'Revenim imediat'] as $view => $text) {
            $html = view($view)->render();

            $this->assertStringContainsString($text, $html);
            $this->assertStringNotContainsString('<nav', $html);
        }
    }

    public function test_the_other_error_pages_render(): void
    {
        foreach ([403, 419, 429] as $code) {
            $this->assertStringContainsString((string) $code, view("errors.{$code}")->render());
        }
    }
}
