<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SlugRedirect;
use App\Support\CatalogFilterChips;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lista de produse a vitrinei: toate produsele, o categorie (cu descendenții ei)
 * sau rezultatele unei căutări. Parametrii invalizi se ignoră (o listă nu
 * trebuie să dea eroare de validare pe un link vechi sau modificat).
 */
class CatalogController extends Controller
{
    private const PER_PAGE = 12;

    private const SORTS = ['newest', 'price_asc', 'price_desc'];

    public function index(Request $request): View
    {
        return $this->render($request, null);
    }

    public function category(Request $request, string $path): View|RedirectResponse
    {
        $category = Category::query()->active()->where('path', $path)->first();

        if ($category === null) {
            return $this->movedCategory($request, $path) ?? abort(404);
        }

        return $this->render($request, $category);
    }

    /** O cale veche de categorie trimite permanent la calea curentă (cu query-ul păstrat). */
    private function movedCategory(Request $request, string $path): ?RedirectResponse
    {
        $targetId = SlugRedirect::targetId(SlugRedirect::CATEGORY, $path);
        $target = $targetId === null ? null : Category::query()->active()->find($targetId);

        if ($target === null || $target->path === null || $target->path === $path) {
            return null;
        }

        $url = route('catalog.category', ['path' => $target->path]);
        $query = $request->getQueryString();

        return redirect()->to($query ? $url.'?'.$query : $url, 301);
    }

    private function render(Request $request, ?Category $category): View
    {
        $filters = $this->filters($request);

        // Baza: produse active cu variante, în categorie (dacă e cazul). Din ea derivă și
        // lista, și numărătorile pentru filtre, ca cifrele să corespundă cu rezultatele.
        $base = Product::query()->active()->whereHas('variants');

        if ($category !== null) {
            $base->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $this->categoryIds($category)));
        }

        $query = (clone $base)->with(['media', 'variants']);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort']);

        $products = $query->paginate(self::PER_PAGE)->withQueryString();

        $categories = Category::query()->active()->orderBy('position')->orderBy('name')->get();
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->get();
        $brandCounts = $this->brandCounts($base, $filters);

        $resetUrl = $category !== null ? route('catalog.category', ['path' => $category->path]) : route('catalog.index');

        $brandNames = [];

        foreach ($brands as $brand) {
            $brandNames[$brand->slug] = $brand->name;
        }

        $chips = CatalogFilterChips::build($resetUrl, $filters, $brandNames);

        return view('catalog.index', [
            'products' => $products,
            'category' => $category,
            'breadcrumbs' => $category !== null ? $this->breadcrumbs($category) : collect(),
            'categoriesByParent' => $categories->groupBy(fn (Category $c) => $c->parent_id ?? 0),
            // Brandurile fără produse în selecția curentă dispar, dar cele bifate rămân vizibile.
            'brands' => $brands->filter(fn (Brand $b) => ($brandCounts[$b->id] ?? 0) > 0 || in_array($b->slug, $filters['brands'], true))->values(),
            'brandCounts' => $brandCounts,
            'priceBounds' => $this->priceBounds($base, $filters),
            'filters' => $filters,
            'chips' => $chips,
            'filtered' => $filters['q'] !== '' || $filters['brands'] !== [] || $filters['min'] !== null
                || $filters['max'] !== null || $filters['in_stock'] || $filters['sort'] !== 'newest',
        ]);
    }

    /**
     * Câte produse are fiecare brand în selecția curentă, fără filtrul de brand
     * (altfel bifarea unui brand ar pune pe zero toate celelalte).
     *
     * @param  Builder<Product>  $base
     * @param  array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string}  $filters
     * @return array<int, int>
     */
    private function brandCounts(Builder $base, array $filters): array
    {
        $query = clone $base;
        $this->applyFilters($query, $filters, skipBrands: true);

        $counts = [];

        foreach ($query->whereNotNull('brand_id')->selectRaw('brand_id, COUNT(*) as total')->groupBy('brand_id')->pluck('total', 'brand_id') as $brandId => $total) {
            $counts[(int) $brandId] = (int) $total;
        }

        return $counts;
    }

    /**
     * Prețul minim și maxim (în lei, rotunjit) din selecția curentă, fără filtrul de preț:
     * capetele glisorului nu trebuie să se strângă pe măsură ce îl miști.
     *
     * @param  Builder<Product>  $base
     * @param  array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string}  $filters
     * @return array{min: int, max: int}
     */
    private function priceBounds(Builder $base, array $filters): array
    {
        $query = (clone $base)->where('min_price', '>', 0);
        $this->applyFilters($query, $filters, skipPrice: true);

        $low = (clone $query)->min('min_price');
        $high = (clone $query)->max('min_price');

        if (! is_numeric($low) || ! is_numeric($high)) {
            return ['min' => 0, 'max' => 0];
        }

        return ['min' => (int) floor(((int) $low) / 100), 'max' => (int) ceil(((int) $high) / 100)];
    }

    /**
     * @return array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string}
     */
    private function filters(Request $request): array
    {
        $brands = collect((array) $request->query('brand', []))
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn (string $v) => Str::limit($v, 100, ''))
            ->values()
            ->take(20)
            ->all();

        $sort = $request->query('sort');

        return [
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
            'brands' => $brands,
            'min' => $this->amount($request->query('min')),
            'max' => $this->amount($request->query('max')),
            'in_stock' => $request->boolean('in_stock'),
            'sort' => is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : 'newest',
        ];
    }

    private function amount(mixed $value): ?int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,7}$/', (string) $value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters, bool $skipBrands = false, bool $skipPrice = false): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like));
            });
        }

        if (! $skipBrands && $filters['brands'] !== []) {
            $ids = Brand::query()->where('is_active', true)->whereIn('slug', $filters['brands'])->pluck('id');
            $query->whereIn('brand_id', $ids);
        }

        // Prețurile din filtru sunt în lei; coloana este în unități minore.
        if (! $skipPrice && $filters['min'] !== null) {
            $query->where('min_price', '>=', $filters['min'] * 100);
        }

        if (! $skipPrice && $filters['max'] !== null) {
            $query->where('min_price', '<=', $filters['max'] * 100);
        }

        if ($filters['in_stock']) {
            $query->where('total_stock', '>', 0);
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('min_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('min_price')->orderBy('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * Categoria și toți descendenții ei activi (după calea materializată).
     *
     * @return Collection<int, int>
     */
    private function categoryIds(Category $category): Collection
    {
        $path = (string) $category->path;

        return Category::query()
            ->active()
            ->where(fn (Builder $q) => $q->where('path', $path)->orWhere('path', 'like', addcslashes($path, '%_\\').'/%'))
            ->pluck('id');
    }

    /**
     * @return Collection<int, Category>
     */
    private function breadcrumbs(Category $category): Collection
    {
        $segments = explode('/', (string) $category->path);
        $paths = [];

        foreach ($segments as $i => $_) {
            $paths[] = implode('/', array_slice($segments, 0, $i + 1));
        }

        return Category::query()->active()->whereIn('path', $paths)->get()
            ->sortBy(fn (Category $c) => strlen((string) $c->path))
            ->values();
    }
}
