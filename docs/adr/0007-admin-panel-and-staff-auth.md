# ADR 0007 – Panoul de administrare și autentificarea personalului

Status: acceptat (Etapa 6, Pachetul 6A).

## Decizii
1. **Filament 5** pe `/admin` (id `admin`, guard `web`). Resursele vor sta în
   `app/Filament/Resources`, paginile în `app/Filament/Pages`, widgeturile în
   `app/Filament/Widgets` (descoperite automat).
2. **Acces.** `User::canAccessPanel()`: cont activ cu permisiunea `access-admin`
   (Super Admin o are prin `Gate::before`). Clienții și conturile dezactivate
   primesc 403.
3. **2FA nativ Filament, obligatoriu** (`isRequired: true`): aplicație de
   autentificare + coduri de recuperare. Coloane noi pe `users`:
   `app_authentication_secret`, `app_authentication_recovery_codes`
   (criptate prin cast, ascunse la serializare). Coloanele Fortify `two_factor_*`
   rămân pentru clienții magazinului. Middleware-ul vechi `admin.2fa` a fost
   eliminat (nu era folosit pe nicio rută).
4. **Fără ocolirea celui de-al doilea factor.** Fortify și Filament împart
   sesiunea `web`. Dacă personalul s-ar putea loga prin Fortify (doar parolă),
   ar putea deschide `/admin` fără provocarea 2FA. De aceea
   `Fortify::authenticateUsing` refuză conturile cu `access-admin` și pe cele
   dezactivate: personalul se loghează exclusiv la `/admin/login`.
5. **Primul cont:** `php artisan admin:create email --role="Super Admin"`
   (parola minim 12 caractere, cerută interactiv sau prin `--password`).
   Contul este activ și cu email verificat; 2FA se configurează la primul login.

## Operațional
- Filament publică asset-urile în `public/` (ignorate de git). La deploy se
  rulează `php artisan filament:upgrade` (Etapa 7).
- Filament a avut mai multe advisory-uri MFA în 2026: se ține la zi
  (`composer audit` în CI și local).
