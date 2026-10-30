<?php

namespace App\Domain\Account;

use App\Models\Address;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carnetul de adrese al unui client: o singură adresă implicită, maximum
 * LIMIT adrese, iar ștergerea celei implicite o promovează pe cea mai veche.
 */
class AddressBook
{
    public const LIMIT = 20;

    /** @return Collection<int, Address> */
    public function forUser(int $userId): Collection
    {
        return Address::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    public function find(int $userId, int $addressId): ?Address
    {
        return Address::query()->where('user_id', $userId)->whereKey($addressId)->first();
    }

    public function isFull(int $userId): bool
    {
        return Address::query()->where('user_id', $userId)->count() >= self::LIMIT;
    }

    /**
     * @param  array<string, mixed>  $data  câmpurile adresei și, opțional, is_default
     */
    public function save(int $userId, array $data, ?Address $address = null): Address
    {
        return DB::transaction(function () use ($userId, $data, $address): Address {
            $isFirst = ! Address::query()->where('user_id', $userId)->exists();
            $wasDefault = $address !== null && $address->is_default;

            $address ??= new Address;
            $address->fill(array_intersect_key($data, array_flip(Address::FIELDS)));
            $address->user_id = $userId;
            // Fără adresă implicită nu se poate: prima adresă o devine automat.
            $address->is_default = $isFirst || $wasDefault || (bool) ($data['is_default'] ?? false);
            $address->save();

            if ($address->is_default) {
                Address::query()->where('user_id', $userId)->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            return $address;
        });
    }

    public function makeDefault(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            Address::query()->where('user_id', $address->user_id)->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->forceFill(['is_default' => true])->save();
        });
    }

    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;
            $address->delete();

            if ($wasDefault) {
                Address::query()->where('user_id', $userId)->orderBy('id')->limit(1)->update(['is_default' => true]);
            }
        });
    }

    /**
     * Salvează adresa de la finalizarea comenzii, dacă nu există deja una
     * identică (stradă + cod poștal + localitate) și carnetul nu e plin.
     *
     * @param  array<string, mixed>  $shipping
     * @param  array<string, mixed>  $billing  conține și datele firmei, când e cazul
     */
    public function rememberFromCheckout(int $userId, array $shipping, array $billing): ?Address
    {
        if ($this->isFull($userId)) {
            return null;
        }

        $data = array_merge($shipping, [
            'company_name' => $billing['company_name'] ?? null,
            'cui' => $billing['cui'] ?? null,
            'reg_com' => $billing['reg_com'] ?? null,
        ]);

        $same = fn (mixed $v): string => mb_strtolower(trim((string) $v));

        $duplicate = $this->forUser($userId)->contains(
            fn (Address $a): bool => $same($a->address_line1) === $same($data['address_line1'] ?? '')
                && $same($a->postal_code) === $same($data['postal_code'] ?? '')
                && $same($a->city) === $same($data['city'] ?? ''),
        );

        return $duplicate ? null : $this->save($userId, $data);
    }

    /**
     * Valorile cu care se precompletează formularul de checkout.
     *
     * @return array<string, string|null>
     */
    public function prefill(?Address $address): array
    {
        return $address === null ? [] : $address->only(Address::FIELDS);
    }
}
