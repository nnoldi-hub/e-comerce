<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia, SoftDeletes;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')->useDisk('public');
        $this->addMediaCollection('thumbnail')->singleFile()->useDisk('public');
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
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
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
