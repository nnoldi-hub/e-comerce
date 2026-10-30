<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\SlugRedirect;
use Livewire\Livewire;

class SlugAdminFormTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->makeStaff('Super Admin'));
    }

    public function test_a_product_gets_a_generated_slug_when_the_field_is_empty(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Telefon Nou', 'type' => 'simple', 'status' => 'draft', 'slug' => ''])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('telefon-nou', Product::query()->where('name', 'Telefon Nou')->value('slug'));
    }

    public function test_a_custom_product_slug_is_kept(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Telefon', 'type' => 'simple', 'status' => 'draft', 'slug' => 'telefon-special'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('telefon-special', Product::query()->where('name', 'Telefon')->value('slug'));
    }

    public function test_invalid_and_duplicate_product_slugs_are_rejected(): void
    {
        Product::create(['name' => 'Existent', 'slug' => 'existent']);

        foreach (['Slug Invalid!', 'cu--dubla', '-incepe', 'existent'] as $slug) {
            Livewire::test(CreateProduct::class)
                ->fillForm(['name' => 'X', 'type' => 'simple', 'status' => 'draft', 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }
    }

    public function test_editing_a_product_slug_saves_it_and_remembers_the_old_address(): void
    {
        $product = Product::create(['name' => 'Telefon', 'slug' => 'telefon-vechi', 'type' => 'simple', 'status' => 'draft']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['slug' => 'telefon-nou'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('telefon-nou', $product->refresh()->slug);
        $this->assertSame($product->id, SlugRedirect::targetId(SlugRedirect::PRODUCT, 'telefon-vechi'));
    }

    public function test_the_slug_is_required_when_editing(): void
    {
        $product = Product::create(['name' => 'Telefon', 'slug' => 'telefon', 'type' => 'simple', 'status' => 'draft']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['slug' => ''])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'required']);
    }

    public function test_a_category_slug_can_be_set_on_create_and_changed_on_edit(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Electronice', 'slug' => 'tech', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $parent = Category::query()->where('name', 'Electronice')->firstOrFail();
        $child = Category::create(['name' => 'Telefoane', 'parent_id' => $parent->id]);
        $this->assertSame('tech', $parent->path);

        Livewire::test(EditCategory::class, ['record' => $parent->getKey()])
            ->fillForm(['slug' => 'electronice-noi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('electronice-noi/telefoane', $child->refresh()->path);
        $this->assertSame($parent->id, SlugRedirect::targetId(SlugRedirect::CATEGORY, 'tech'));
        $this->assertSame($child->id, SlugRedirect::targetId(SlugRedirect::CATEGORY, 'tech/telefoane'));
    }

    public function test_duplicate_category_slugs_are_rejected(): void
    {
        Category::create(['name' => 'Prima']);

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'A doua', 'slug' => 'prima', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }
}
