<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia, SoftDeletes;

    /** Tipurile de imagine acceptate (și în admin, și la nivel de colecție). */
    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->useDisk('public')
            ->acceptsMimeTypes(self::IMAGE_MIME_TYPES);

        $this->addMediaCollection('thumbnail')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(self::IMAGE_MIME_TYPES);
    }

    /**
     * `thumb` se generează imediat (îl folosește panoul de administrare, deci
     * nu poate aștepta un worker); `card` și `large` merg în coadă.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Manipulările (fit/width) merg la sfârșit: sunt apeluri magice și
        // schimbă tipul lanțului, deci opțiunile conversiei se pun înaintea lor.
        $this->addMediaConversion('thumb')
            ->performOnCollections('gallery', 'thumbnail')
            ->nonQueued()
            ->fit(Fit::Crop, 160, 160);

        $this->addMediaConversion('card')
            ->performOnCollections('gallery', 'thumbnail')
            ->width(480);

        $this->addMediaConversion('large')
            ->performOnCollections('gallery', 'thumbnail')
            ->width(1200);
    }

    protected $fillable = ['ulid', 'brand_id', 'name', 'slug', 'description', 'type', 'status', 'seo', 'published_at', 'min_price', 'total_stock'];

    protected $casts = [
        'seo' => 'array',
        'published_at' => 'datetime',
        'min_price' => 'integer',
        'total_stock' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->ulid)) {
                $product->ulid = (string) Str::ulid();
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        // Slug stabil: redenumirea produsului nu schimbă URL-ul (SEO, link-uri salvate).
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function defaultVariant(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_default', true);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('position');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
