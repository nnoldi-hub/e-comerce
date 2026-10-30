<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pagină de conținut (termeni, confidențialitate, livrare și retur, contact).
 * Conținutul este Markdown; HTML-ul din el este eliminat la afișare.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $meta_description
 * @property bool $is_published
 * @property bool $show_in_footer
 * @property int $position
 */
class ContentPage extends Model
{
    public const FOOTER_CACHE_KEY = 'footer.pages';

    public const TERMS = 'termeni-si-conditii';

    public const PRIVACY = 'politica-de-confidentialitate';

    protected $fillable = ['title', 'slug', 'content', 'meta_description', 'is_published', 'show_in_footer', 'position'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $forget = function (): void {
            Cache::forget(self::FOOTER_CACHE_KEY);
            Cache::forget('sitemap.xml');
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @param  Builder<ContentPage>  $query
     * @return Builder<ContentPage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Adresa publică a paginii sau null dacă nu există / nu e publicată. */
    public static function urlFor(string $slug): ?string
    {
        $exists = self::query()->published()->where('slug', $slug)->exists();

        return $exists ? route('pages.show', $slug) : null;
    }

    /** @return list<array{title: string, slug: string}> */
    public static function footerLinks(): array
    {
        /** @var list<array{title: string, slug: string}> */
        return Cache::remember(self::FOOTER_CACHE_KEY, 600, fn (): array => self::query()
            ->published()
            ->where('show_in_footer', true)
            ->orderBy('position')->orderBy('id')
            ->get(['title', 'slug'])
            ->map(fn (self $page): array => ['title' => $page->title, 'slug' => $page->slug])
            ->all());
    }

    /** Markdown → HTML sigur: HTML-ul brut este eliminat, iar linkurile nesigure (javascript:) nu se randează. */
    public function html(): string
    {
        return Str::markdown($this->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
