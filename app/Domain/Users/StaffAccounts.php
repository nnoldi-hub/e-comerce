<?php

namespace App\Domain\Users;

use App\Domain\Users\Exceptions\InvalidStaffChangeException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Singurul loc în care se creează și se modifică conturile de personal.
 *
 * Reguli:
 *  - doar cine are `users.manage` poate gestiona personalul;
 *  - rolul „Client” nu se atribuie de aici, iar un cont de personal are cel puțin un rol;
 *  - doar un Super Admin poate atribui rolul Super Admin sau modifica un Super Admin;
 *  - nu îți poți dezactiva contul și nu îți poți schimba rolurile;
 *  - trebuie să rămână cel puțin un Super Admin activ.
 * Conturile nu se șterg (istoricul din audit trebuie să rămână atribuibil), doar se dezactivează.
 */
class StaffAccounts
{
    public const SUPER_ADMIN = 'Super Admin';

    public const CUSTOMER_ROLE = 'Client';

    private const FIELDS = ['name', 'email', 'password', 'phone', 'is_active'];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roleNames
     *
     * @throws InvalidStaffChangeException
     */
    public function create(User $actor, array $attributes, array $roleNames): User
    {
        $roles = array_values(array_unique($roleNames));
        $active = (bool) ($attributes['is_active'] ?? true);

        $this->assertAllowed($actor, null, $roles, $active);

        return DB::transaction(function () use ($actor, $attributes, $roles, $active): User {
            $user = new User;
            $user->fill(Arr::only($attributes, self::FIELDS));
            $user->is_active = $active;
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();
            $user->syncRoles($roles);

            activity('staff')
                ->causedBy($actor)
                ->performedOn($user)
                ->event('created')
                ->withProperties(['roles' => $roles, 'is_active' => $active])
                ->log('Cont de personal creat');

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roleNames
     *
     * @throws InvalidStaffChangeException
     */
    public function update(User $actor, User $target, array $attributes, array $roleNames): User
    {
        $roles = array_values(array_unique($roleNames));
        $active = array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : (bool) $target->is_active;

        $this->assertAllowed($actor, $target, $roles, $active);

        return DB::transaction(function () use ($actor, $target, $attributes, $roles, $active): User {
            $data = Arr::only($attributes, self::FIELDS);

            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }

            $before = $this->roleNames($target);

            $target->fill($data);
            $target->is_active = $active;
            $target->save();
            $target->syncRoles($roles);

            activity('staff')
                ->causedBy($actor)
                ->performedOn($target)
                ->event('updated')
                ->withProperties([
                    'roles_before' => $before,
                    'roles_after' => $roles,
                    'is_active' => $active,
                    'password_changed' => array_key_exists('password', $data),
                ])
                ->log('Cont de personal modificat');

            return $target;
        });
    }

    /**
     * Șterge secretul 2FA: la următoarea conectare, utilizatorul trebuie să
     * configureze din nou aplicația de autentificare (2FA e obligatoriu).
     *
     * @throws InvalidStaffChangeException
     */
    public function resetMfa(User $actor, User $target): void
    {
        $this->assertMayManage($actor);

        if ($actor->is($target)) {
            throw new InvalidStaffChangeException('Nu îți poți reseta propriul 2FA din această listă; folosește pagina de profil.');
        }

        if ($target->hasRole(self::SUPER_ADMIN) && ! $actor->hasRole(self::SUPER_ADMIN)) {
            throw new InvalidStaffChangeException('Doar un Super Admin poate reseta 2FA pentru un Super Admin.');
        }

        $target->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();

        activity('staff')
            ->causedBy($actor)
            ->performedOn($target)
            ->event('mfa-reset')
            ->log('2FA resetat');
    }

    /**
     * Numele rolurilor care se pot atribui personalului.
     *
     * @return list<string>
     */
    public static function assignableRoles(): array
    {
        return Role::query()
            ->where('name', '!=', self::CUSTOMER_ROLE)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function roleNames(User $user): array
    {
        return $user->roles()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @throws InvalidStaffChangeException
     */
    private function assertMayManage(User $actor): void
    {
        if (! $actor->can('users.manage')) {
            throw new InvalidStaffChangeException('Nu ai permisiunea de a gestiona personalul.');
        }
    }

    /**
     * @param  list<string>  $roles
     *
     * @throws InvalidStaffChangeException
     */
    private function assertAllowed(User $actor, ?User $target, array $roles, bool $active): void
    {
        $this->assertMayManage($actor);

        if ($roles === []) {
            throw new InvalidStaffChangeException('Alege cel puțin un rol.');
        }

        if (array_diff($roles, self::assignableRoles()) !== []) {
            throw new InvalidStaffChangeException('Unul dintre roluri nu există sau nu poate fi atribuit personalului.');
        }

        $actorIsSuper = $actor->hasRole(self::SUPER_ADMIN);

        if (! $actorIsSuper && (in_array(self::SUPER_ADMIN, $roles, true) || ($target !== null && $target->hasRole(self::SUPER_ADMIN)))) {
            throw new InvalidStaffChangeException('Doar un Super Admin poate atribui rolul Super Admin sau modifica un Super Admin.');
        }

        if ($target === null) {
            return;
        }

        if ($actor->is($target)) {
            if (! $active) {
                throw new InvalidStaffChangeException('Nu îți poți dezactiva propriul cont.', 'is_active');
            }

            $current = $this->roleNames($target);
            $wanted = $roles;
            sort($wanted);

            if ($current !== $wanted) {
                throw new InvalidStaffChangeException('Nu îți poți schimba propriile roluri.');
            }
        }

        if ($target->hasRole(self::SUPER_ADMIN) && (! in_array(self::SUPER_ADMIN, $roles, true) || ! $active)) {
            $others = User::query()
                ->where('is_active', true)
                ->whereKeyNot($target->getKey())
                ->whereHas('roles', fn (Builder $query) => $query->where('name', self::SUPER_ADMIN))
                ->count();

            if ($others === 0) {
                throw new InvalidStaffChangeException('Trebuie să rămână cel puțin un Super Admin activ.', $active ? 'role_names' : 'is_active');
            }
        }
    }
}
