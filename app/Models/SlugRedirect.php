<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Adrese vechi (slug de produs sau cale de categorie) care trimit cu 301 la
 * adresa curentă a elementului. Se reține doar ținta (id-ul), nu adresa nouă,
 * deci lanțurile de redenumiri A→B→C duc toate la C.
 *
 * @property int $id
 * @property string $type
 * @property string $old_key
 * @property int $target_id
 */
class SlugRedirect extends Model
{
    use HasFactory;

    public const PRODUCT = 'product';

    public const CATEGORY = 'category';

    protected $fillable = ['type', 'old_key', 'target_id'];

    protected function casts(): array
    {
        return ['target_id' => 'integer'];
    }

    public static function remember(string $type, ?string $oldKey, int $targetId): void
    {
        if ($oldKey === null || $oldKey === '') {
            return;
        }

        self::query()->updateOrCreate(['type' => $type, 'old_key' => $oldKey], ['target_id' => $targetId]);
    }

    /** Adresa a fost (re)luată de un element viu: nu mai trebuie redirecționată. */
    public static function forget(string $type, ?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        self::query()->where('type', $type)->where('old_key', $key)->delete();
    }

    public static function targetId(string $type, string $key): ?int
    {
        $id = self::query()->where('type', $type)->where('old_key', $key)->value('target_id');

        return $id === null ? null : (int) $id;
    }
}
