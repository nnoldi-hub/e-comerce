<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Category extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = ['parent_id', 'name', 'slug', 'path', 'position', 'is_active', 'meta_title', 'meta_description'];

    protected $casts = [
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if ($category->parent_id === null) {
                return;
            }

            // O categorie nu poate fi propriul părinte sau descendentul său.
            $cursor = $category->parent_id;
            $guard = 0;

            while ($cursor !== null && $guard++ < 100) {
                if ($category->exists && (int) $cursor === (int) $category->id) {
                    throw new InvalidArgumentException('O categorie nu poate fi mutată sub ea însăși sau sub un descendent.');
                }

                $cursor = static::query()->whereKey($cursor)->value('parent_id');
            }
        });

        static::saved(function (Category $category): void {
            $category->syncPath();
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * `path` este calea materializată din sluguri: "electronice/telefoane".
     * Se recalculează pentru categorie și pentru toți descendenții.
     */
    public function syncPath(): void
    {
        $parentPath = $this->parent_id !== null
            ? static::query()->whereKey($this->parent_id)->value('path')
            : null;

        $path = $parentPath !== null && $parentPath !== '' ? $parentPath.'/'.$this->slug : $this->slug;

        if ($this->path !== $path) {
            static::query()->whereKey($this->id)->update(['path' => $path]);
            $this->path = $path;
            $this->syncOriginalAttribute('path');
        }

        /** @var Collection<int, Category> $children */
        $children = $this->children()->get();

        foreach ($children as $child) {
            $child->syncPath();
        }
    }

    /** Reconstruiește `path` pentru tot arborele (după import sau date vechi). */
    public static function rebuildPaths(): int
    {
        $roots = static::query()->whereNull('parent_id')->get();
        $roots->each(fn (Category $root) => $root->syncPath());

        return static::query()->count();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('position');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
