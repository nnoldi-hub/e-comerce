<?php

namespace Tests\Feature\Storefront;

use App\Filament\Resources\ContentPages\Pages\CreateContentPage;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Models\ContentPage;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Feature\Admin\AdminTestCase;

class ContentPagesTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(ContentPage::FOOTER_CACHE_KEY);
        Cache::forget('sitemap.xml');
    }

    private function page(array $overrides = []): ContentPage
    {
        return ContentPage::create(array_merge([
            'title' => 'Despre noi',
            'slug' => 'despre-noi',
            'content' => "## Salut\n\nText **important**.",
            'is_published' => true,
            'show_in_footer' => true,
            'position' => 0,
        ], $overrides));
    }

    public function test_a_published_page_renders_markdown(): void
    {
        $this->page();

        $this->get('/pagina/despre-noi')
            ->assertOk()
            ->assertSee('Despre noi')
            ->assertSee('<h2>Salut</h2>', false)
            ->assertSee('<strong>important</strong>', false);
    }

    public function test_unpublished_and_unknown_pages_are_not_found(): void
    {
        $this->page(['slug' => 'ciorna', 'is_published' => false]);

        $this->get('/pagina/ciorna')->assertNotFound();
        $this->get('/pagina/nu-exista')->assertNotFound();
    }

    public function test_html_and_unsafe_links_in_content_are_neutralised(): void
    {
        $this->page([
            'slug' => 'xss',
            'content' => "<script>alert('x')</script>\n\n[click](javascript:alert(1))\n\n<img src=x onerror=alert(1)>",
        ]);

        $html = $this->get('/pagina/xss')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_the_footer_lists_only_published_footer_pages_in_order(): void
    {
        $this->page(['title' => 'Contact', 'slug' => 'contact', 'position' => 2]);
        $this->page(['title' => 'Livrare', 'slug' => 'livrare', 'position' => 1]);
        $this->page(['title' => 'Ciorna', 'slug' => 'ciorna', 'is_published' => false]);
        $this->page(['title' => 'Ascunsa', 'slug' => 'ascunsa', 'show_in_footer' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('/pagina/contact', $html);
        $this->assertStringContainsString('/pagina/livrare', $html);
        $this->assertStringNotContainsString('/pagina/ciorna', $html);
        $this->assertStringNotContainsString('/pagina/ascunsa', $html);
        $this->assertLessThan(strpos($html, '/pagina/contact'), strpos($html, '/pagina/livrare'));
    }

    public function test_saving_a_page_refreshes_the_footer_immediately(): void
    {
        $page = $this->page(['is_published' => false]);
        $this->get('/')->assertDontSee('/pagina/despre-noi', false);

        $page->update(['is_published' => true]);

        $this->get('/')->assertSee('/pagina/despre-noi', false);
    }

    public function test_the_sitemap_lists_published_pages_only(): void
    {
        $this->page();
        $this->page(['slug' => 'ciorna', 'is_published' => false]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/pagina/despre-noi', false)
            ->assertDontSee('/pagina/ciorna', false);
    }

    public function test_the_legal_pages_seeder_creates_unpublished_drafts_and_is_idempotent(): void
    {
        $this->seed(LegalPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);

        $this->assertSame(4, ContentPage::query()->count());
        $this->assertSame(0, ContentPage::query()->where('is_published', true)->count());
        $this->assertTrue(ContentPage::query()->where('slug', ContentPage::TERMS)->exists());
        $this->assertTrue(ContentPage::query()->where('slug', ContentPage::PRIVACY)->exists());

        ContentPage::query()->where('slug', 'contact')->update(['content' => 'Personalizat']);
        $this->seed(LegalPagesSeeder::class);
        $this->assertSame('Personalizat', ContentPage::query()->where('slug', 'contact')->value('content'));
    }

    public function test_only_super_admin_manages_pages(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));
        $this->get('/admin/content-pages')->assertOk();
        $this->get('/admin/content-pages/create')->assertOk();

        foreach (['Manager Inventar', 'Suport Clienți'] as $role) {
            $this->actingAs($this->makeStaff($role));
            $this->get('/admin/content-pages')->assertForbidden();
        }
    }

    public function test_the_slug_is_generated_from_the_title_and_kept_unique(): void
    {
        $this->actingAs($this->makeStaff());
        $this->page(['title' => 'Contact', 'slug' => 'contact']);

        Livewire::test(CreateContentPage::class)
            ->fillForm(['title' => 'Contact', 'slug' => '', 'content' => 'Text', 'position' => 0])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(ContentPage::query()->where('slug', 'contact-2')->exists());
    }

    public function test_an_invalid_or_duplicate_slug_is_rejected(): void
    {
        $this->actingAs($this->makeStaff());
        $this->page();

        Livewire::test(CreateContentPage::class)
            ->fillForm(['title' => 'X', 'slug' => 'Slug Invalid!', 'content' => 'Text', 'position' => 0])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        Livewire::test(CreateContentPage::class)
            ->fillForm(['title' => 'X', 'slug' => 'despre-noi', 'content' => 'Text', 'position' => 0])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_a_page_can_be_edited_and_published(): void
    {
        $this->actingAs($this->makeStaff());
        $page = $this->page(['is_published' => false]);

        Livewire::test(EditContentPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Despre noi acum', 'is_published' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertTrue($page->is_published);
        $this->assertSame('despre-noi', $page->slug);
        $this->get('/pagina/despre-noi')->assertOk()->assertSee('Despre noi acum');
    }
}
