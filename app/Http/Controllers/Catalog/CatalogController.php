<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
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

    public function category(Request $request, string $path): View
    {
        $category = Category::query()->active()->where('path', $path)->firstOrFail();

        return $this->render($request, $category);
    }

    private function render(Request $request, ?Category $category): View
    {
        $filters = $this->filters($request);

        $query = Product::query()
            ->active()
            ->whereHas('variants')
            ->with('media');

        if ($category !== null) {
            $query->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $this->categoryIds($category)));
        }

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort']);

        $products = $query->paginate(self::PER_PAGE)->withQueryString();

        $categories = Category::query()->active()->orderBy('position')->orderBy('name')->get();

        return view('catalog.index', [
            'products' => $products,
            'category' => $category,
            'breadcrumbs' => $category !== null ? $this->breadcrumbs($category) : collect(),
            'categoriesByParent' => $categories->groupBy(fn (Category $c) => $c->parent_id ?? 0),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $filters,
            'filtered' => $filters['q'] !== '' || $filters['brands'] !== [] || $filters['min'] !== null
                || $filters['max'] !== null || $filters['in_stock'] || $filters['sort'] !== 'newest',
        ]);
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
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like));
            });
        }

        if ($filters['brands'] !== []) {
            $ids = Brand::query()->where('is_active', true)->whereIn('slug', $filters['brands'])->pluck('id');
            $query->whereIn('brand_id', $ids);
        }

        // Prețurile din filtru sunt în lei; coloana este în unități minore.
        if ($filters['min'] !== null) {
            $query->where('min_price', '>=', $filters['min'] * 100);
        }

        if ($filters['max'] !== null) {
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
