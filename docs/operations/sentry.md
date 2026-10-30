# Sentry (opțional)

Sentry raportează erorile din producție (cu stack trace, utilizator și cerere). Integrarea este **opțională**:
fără DSN nu se trimite nimic.

## Activare

1. `composer require sentry/sentry-laravel`
2. În `bootstrap/app.php`, în `->withExceptions(...)`:

   ```php
   ->withExceptions(function (Exceptions $exceptions): void {
       \Sentry\Laravel\Integration::handles($exceptions);
   })
   ```
3. În `.env` de producție:

   ```dotenv
   SENTRY_LARAVEL_DSN=https://...@....ingest.sentry.io/...
   SENTRY_ENVIRONMENT=production
   SENTRY_TRACES_SAMPLE_RATE=0.0
   SENTRY_SEND_DEFAULT_PII=false
   ```
4. Verificare: `php artisan sentry:test`.

## Confidențialitate

- `SENTRY_SEND_DEFAULT_PII=false` (implicit): nu se trimit IP-uri, cookie-uri, email-uri.
- Datele de plată nu trec prin aplicație (Stripe), iar parolele nu apar în excepții.
- Excepțiile `ValidationException`, `AuthenticationException` și 404 nu se raportează implicit.
- Pentru GDPR: alege regiunea UE a proiectului Sentry și semnează DPA-ul.
