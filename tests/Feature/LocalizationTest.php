<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Apără traducerile în română: fișierele există, sunt complete față de
 * engleza din framework și chiar sunt folosite de validator.
 * Dacă după un upgrade de Laravel apar chei noi, testul de completitudine
 * pică și îți spune să rulezi `php artisan lang:update`.
 */
class LocalizationTest extends TestCase
{
    private const FILES = ['auth', 'pagination', 'passwords', 'validation'];

    public function test_the_application_is_romanian_with_an_english_fallback(): void
    {
        $this->assertSame('ro', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('ro', config('app.admin_locale'));
    }

    public function test_the_romanian_translation_files_are_published(): void
    {
        foreach (self::FILES as $file) {
            $this->assertFileExists(lang_path("ro/{$file}.php"), "Lipsește lang/ro/{$file}.php (rulează `php artisan lang:add ro`).");
        }
    }

    public function test_romanian_covers_every_key_of_the_framework_english_files(): void
    {
        $source = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        if (! is_dir($source)) {
            $this->markTestSkipped('Fișierele en din framework nu sunt la locul așteptat.');
        }

        foreach (self::FILES as $file) {
            $english = require "{$source}/{$file}.php";
            $romanian = require lang_path("ro/{$file}.php");

            // `attributes` (listă goală) și `custom.*` (exemplu de șablon) nu sunt mesaje.
            $required = array_filter(
                array_keys(Arr::dot($english)),
                fn (string $key): bool => $key !== 'attributes' && ! str_starts_with($key, 'custom.'),
            );

            $missing = array_diff($required, array_keys(Arr::dot($romanian)));

            $this->assertSame([], array_values($missing), "lang/ro/{$file}.php nu are cheile: ".implode(', ', $missing).' (rulează `php artisan lang:update`).');
        }
    }

    public function test_validation_messages_come_out_in_romanian(): void
    {
        $this->app->setLocale('ro');

        $messages = Validator::make(['email' => 'nu-e-email'], ['name' => 'required', 'email' => 'email', 'age' => 'required|integer'])
            ->errors()
            ->all();

        $this->assertCount(3, $messages);

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('field', $message);
            $this->assertStringNotContainsString('must be', $message);
            $this->assertStringNotContainsString('validation.', $message);
        }
    }

    public function test_auth_and_password_messages_are_translated(): void
    {
        $this->app->setLocale('ro');

        foreach (['auth.failed', 'auth.throttle', 'passwords.reset', 'passwords.user'] as $key) {
            $translated = trans($key, ['seconds' => 5, 'minutes' => 1]);

            $this->assertNotSame($key, $translated, "Cheia {$key} nu se traduce.");
            $this->assertStringNotContainsStringIgnoringCase('credentials', $translated);
            $this->assertStringNotContainsStringIgnoringCase('password', $translated);
        }
    }
}
