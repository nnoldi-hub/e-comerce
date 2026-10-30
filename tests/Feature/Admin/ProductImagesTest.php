<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductImagesTest extends ReportingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function product(): Product
    {
        return Product::query()->create(['name' => 'Tricou test', 'type' => 'simple', 'status' => 'draft']);
    }

    private function mediaIn(Product $product, string $collection): int
    {
        return Media::query()
            ->where('model_type', $product->getMorphClass())
            ->where('model_id', $product->getKey())
            ->where('collection_name', $collection)
            ->count();
    }

    public function test_the_gallery_accepts_images_and_rejects_other_files(): void
    {
        $product = $this->product();

        $product->addMedia(UploadedFile::fake()->image('a.jpg', 800, 600))->toMediaCollection('gallery');
        $product->addMedia(UploadedFile::fake()->image('b.png', 400, 400))->toMediaCollection('gallery');

        $this->assertSame(2, $this->mediaIn($product, 'gallery'));

        $this->expectException(FileUnacceptableForCollection::class);
        $product->addMedia(UploadedFile::fake()->create('catalog.pdf', 10, 'application/pdf'))->toMediaCollection('gallery');
    }

    public function test_the_thumbnail_collection_keeps_a_single_file(): void
    {
        $product = $this->product();

        $product->addMedia(UploadedFile::fake()->image('one.jpg'))->toMediaCollection('thumbnail');
        $product->addMedia(UploadedFile::fake()->image('two.jpg'))->toMediaCollection('thumbnail');

        $this->assertSame(1, $this->mediaIn($product, 'thumbnail'));
    }

    public function test_the_thumb_conversion_is_generated_immediately(): void
    {
        $product = $this->product();

        $media = $product->addMedia(UploadedFile::fake()->image('a.jpg', 800, 600))->toMediaCollection('gallery');

        $fresh = Media::query()->findOrFail($media->getKey());

        $this->assertTrue($fresh->hasGeneratedConversion('thumb'));
        Storage::disk('public')->assertExists($fresh->getPathRelativeToRoot('thumb'));
    }

    public function test_staff_can_upload_images_from_the_edit_page(): void
    {
        $product = $this->product();
        $this->actingAs($this->staff('Manager Inventar'));

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'thumbnail' => UploadedFile::fake()->image('main.jpg', 800, 600),
                'gallery' => [
                    UploadedFile::fake()->image('g1.jpg', 800, 600),
                    UploadedFile::fake()->image('g2.png', 800, 600),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $this->mediaIn($product, 'thumbnail'));
        $this->assertSame(2, $this->mediaIn($product, 'gallery'));
    }
}
