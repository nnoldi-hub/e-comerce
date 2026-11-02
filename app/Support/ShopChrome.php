<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ContentPage;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Datele de care are nevoie „ramă” vitrinei (header, meniu, pagina principală):
 * categoriile principale și numărul de produse din coș.
 *
 * Citește doar: nu creează niciodată un coș. `CartService::getCurrentCart()`
 * face `firstOrCreate`, deci apelat din header ar crea câte un rând în `carts`
 * pentru fiecare vizitator (și bot) care deschide orice pagină.
 *
 * `Scoped` = o singură instanță pe cerere, deci interogările rulează cel mult o dată.
 */
#[Scoped]
final class ShopChrome
{
    private const MAX_CATEGORIES = 6;

    /** Categorii principale în meniul mare; subcategoriile se limitează în vedere. */
    private const MAX_MENU = 8;

    /** @var Collection<int, Category>|null */
    private ?Collection $categories = null;

    /** @var Collection<int, Category>|null */
    private ?Collection $menu = null;

    private ?int $cartCount = null;

    /** @var list<array{icon: string, short: string, title: string, text: string, url: string|null}>|null */
    private ?array $benefits = null;

    /**
     * Categoriile principale active, în ordinea din admin.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return $this->categories ??= Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('name')
            ->limit(self::MAX_CATEGORIES)
            ->get(['id', 'name', 'path', 'image_path']);
    }

    /**
     * Meniul mare: categoriile principale active, fiecare cu subcategoriile active
     * deja încărcate (`children`), ca vederea să nu facă interogări în plus.
     *
     * @return Collection<int, Category>
     */
    public function menu(): Collection
    {
        $columns = ['id', 'parent_id', 'name', 'path', 'image_path', 'position'];

        return $this->menu ??= Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('name')
            ->limit(self::MAX_MENU)
            ->with(['children' => fn ($children) => $children->where('is_active', true)->orderBy('position')->orderBy('name')->select($columns)])
            ->get($columns);
    }

    /**
     * Beneficiile comerciale din `config/storefront.php`. Linkul către pagina de
     * conținut apare doar dacă pagina e publicată (o singură interogare pe cerere).
     *
     * @return list<array{icon: string, short: string, title: string, text: string, url: string|null}>
     */
    public function benefits(): array
    {
        if ($this->benefits !== null) {
            return $this->benefits;
        }

        $configured = config('storefront.benefits', []);
        $rows = [];

        foreach (is_array($configured) ? $configured : [] as $row) {
            if (is_array($row) && isset($row['icon'], $row['short'], $row['title'], $row['text'])) {
                $rows[] = $row;
            }
        }

        $slugs = [];

        foreach ($rows as $row) {
            if (! empty($row['page']) && is_string($row['page'])) {
                $slugs[] = $row['page'];
            }
        }

        $published = $slugs === []
            ? []
            : ContentPage::query()->published()->whereIn('slug', array_unique($slugs))->pluck('slug')->all();

        $benefits = [];

        foreach ($rows as $row) {
            $page = isset($row['page']) && is_string($row['page']) ? $row['page'] : null;

            $benefits[] = [
                'icon' => (string) $row['icon'],
                'short' => (string) $row['short'],
                'title' => (string) $row['title'],
                'text' => (string) $row['text'],
                'url' => $page !== null && in_array($page, $published, true) ? route('pages.show', $page) : null,
            ];
        }

        return $this->benefits = $benefits;
    }

    /** Numărul total de bucăți din coșul vizitatorului curent (0 dacă nu are coș). */
    public function cartCount(): int
    {
        return $this->cartCount ??= $this->countItems();
    }

    private function countItems(): int
    {
        $sessionId = Session::getId();
        $userId = Auth::id();

        // Un client autentificat poate avea și un coș de sesiune încă nefuzionat
        // (se unește la prima deschidere a coșului), așa că îl numărăm și pe el.
        $cartIds = Cart::query()
            ->select('id')
            ->where(function (Builder $q) use ($sessionId, $userId): void {
                $q->where(fn (Builder $guest) => $guest->where('session_id', $sessionId)->whereNull('user_id'));

                if ($userId !== null) {
                    $q->orWhere('user_id', $userId);
                }
            });

        return (int) CartItem::query()->whereIn('cart_id', $cartIds)->sum('quantity');
    }
}
