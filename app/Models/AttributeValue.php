<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class AttributeValue extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = ['attribute_id', 'value', 'slug'];

    public function getSlugOptions(): SlugOptions
    {
        // Unicitatea este per atribut (indexul unic este [attribute_id, slug]):
        // "M" la Mărime și "M" la altceva nu trebuie să devină "m-1".
        return SlugOptions::create()
            ->generateSlugsFrom('value')
            ->saveSlugsTo('slug')
            ->extraScope(fn ($builder) => $builder->where('attribute_id', $this->attribute_id));
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
