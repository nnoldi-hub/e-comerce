<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adresă din carnetul clientului. Comenzile păstrează propria copie a adresei
 * (JSON pe comandă), deci modificarea sau ștergerea de aici nu le schimbă.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $label
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string|null $company_name
 * @property string|null $cui
 * @property string|null $reg_com
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string $city
 * @property string|null $county
 * @property string $postal_code
 * @property string $country
 * @property bool $is_default
 */
class Address extends Model
{
    /** Câmpurile care alcătuiesc adresa (fără user_id și is_default). */
    public const FIELDS = [
        'label', 'first_name', 'last_name', 'phone', 'company_name', 'cui', 'reg_com',
        'address_line1', 'address_line2', 'city', 'county', 'postal_code', 'country',
    ];

    protected $fillable = [...self::FIELDS, 'user_id', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
