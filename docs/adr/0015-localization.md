# ADR 0015 – Localizare în română

Status: acceptat.

- **Limba aplicației este româna** (`APP_LOCALE=ro`, implicit în `config/app.php`,
  deci și în CI, unde nu există `.env`); engleza rămâne doar `fallback_locale`.
  Panoul de administrare are în plus `app.admin_locale` (implicit `ro`), aplicat
  cererilor panoului, ca să poată diferi de vitrină dacă magazinul devine
  multilingv.
- **Traduceri: `laravel-lang/common`**, instalat în `require-dev`. Pachetul se
  folosește la dezvoltare pentru a publica fișierele `lang/ro/*` (validări,
  autentificare, parole, paginare, atribute, statusuri HTTP), care **se comit în
  repo**. Producția nu depinde de pachet și nu are nevoie de acces la rețea ca să
  fie tradusă.
- **Filament** își aduce propriile traduceri în română; nu se publică și nu se
  copiază.
- **Actualizare:** după fiecare upgrade de Laravel, Filament sau
  `laravel-lang/common`, rulează `php artisan lang:update`, apoi `pint` și
  commit. Nu se adaugă `lang:update` în scripturile Composer, fiindcă
  pachetul este doar pentru dezvoltare și comanda nu există în producție.
- **Gardă:** `LocalizationTest` pică dacă lipsesc fișierele `lang/ro` sau dacă
  româna nu acoperă toate cheile din fișierele engleze ale framework-ului, adică
  exact situația de după un upgrade făcut fără `lang:update`.
- **Mesajele proprii** (cuvintele din interfață, emailuri) rămân scrise direct în
  română în cod, ca până acum. Nu se introduce un sistem de chei `__()` pentru ele
  până nu există o a doua limbă; fișierele de traducere ar fi doar zgomot.
- **Nu e în această etapă:** a doua limbă, selector de limbă, URL-uri pe limbă,
  traducerea numelor de produse și categorii.
