<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Creează un cont de personal. Primul login se face la /admin/login, iar
 * panoul cere imediat configurarea 2FA.
 */
class CreateAdminUser extends Command
{
    private const MIN_PASSWORD_LENGTH = 12;

    protected $signature = 'admin:create {email : Adresa de email}
                            {--name= : Numele afișat (implicit: partea dinaintea @)}
                            {--role=Super Admin : Rolul (ex. "Super Admin", "Manager Inventar", "Suport Clienți")}
                            {--password= : Parola (altfel este cerută interactiv)}';

    protected $description = 'Creează un cont de personal pentru panoul de administrare';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Adresa de email nu este validă.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Există deja un utilizator cu adresa {$email}.");

            return self::FAILURE;
        }

        $roleName = (string) $this->option('role');
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            $this->error("Rolul „{$roleName}” nu există. Rulează mai întâi: php artisan db:seed --class=RolesAndPermissionsSeeder");

            return self::FAILURE;
        }

        $password = $this->option('password');

        if ($password === null) {
            $password = (string) $this->secret('Parola (minim '.self::MIN_PASSWORD_LENGTH.' caractere)');

            if ($password !== (string) $this->secret('Confirmă parola')) {
                $this->error('Parolele nu coincid.');

                return self::FAILURE;
            }
        }

        if (mb_strlen((string) $password) < self::MIN_PASSWORD_LENGTH) {
            $this->error('Parola trebuie să aibă cel puțin '.self::MIN_PASSWORD_LENGTH.' caractere.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));

        $user = User::create([
            'name' => $name !== '' ? $name : Str::before($email, '@'),
            'email' => $email,
            'password' => (string) $password,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        $this->info("Cont creat: {$email} ({$roleName}). Autentifică-te la /admin/login și configurează 2FA.");

        return self::SUCCESS;
    }
}
