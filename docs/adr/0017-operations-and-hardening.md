# ADR 0017 — Operare și întărire pentru producție

Stare: acceptat (Etapa 7B-2/7C/7D)

## Context
Aplicația rulează în spatele Nginx/PHP-FPM, cu coadă și planificator care trebuie să meargă permanent. Fără verificări explicite, un worker oprit sau un cron lipsă trece neobservat până se plâng clienții.

## Decizii
1. **Proxy-uri de încredere din `config('security.trusted_proxies')`**, aplicate de un middleware global (`ConfigureTrustedProxies`), nu `env()` în `bootstrap/app.php` (nesigur cu `config:cache`). Implicit niciunul; `*` înseamnă „proxy-ul care se conectează” (REMOTE_ADDR), nu orice sursă.
2. **Limitatoare de rată numite** (`cart`, `checkout`, `webhooks`) per IP, cu valori în config/env. `/health` are limită proprie.
3. **`HealthCheck`** (domeniu) este sursa unică pentru comanda `ops:check`, `GET /health` și verificarea de producție. Starea: ok/warn/fail; `fail` ⇒ 503 / cod de ieșire non-zero.
4. **Bătaie de inimă a planificatorului** în cache la fiecare minut; stale > 180 s ⇒ fail. Coada (driver database): cel mai vechi job disponibil > 300 s ⇒ fail; joburi eșuate în ultima oră ⇒ warn.
5. **`/health` cere token Bearer** și răspunde 404 altfel, ca să nu expună detalii de infrastructură. `/up` rămâne public.
6. **CI**: PHP 8.3 (ca producția), `composer audit --no-dev` blochează, auditul complet e informativ (abandonate dev-only), pas de smoke `optimize`; Dependabot săptămânal.
7. **Backup**: dump cu `--single-transaction`, parola prin fișier temporar de opțiuni, validare gzip, retenție; rulat și înainte de fiecare migrare din `deploy.sh`.

## Consecințe
- Orice job/cron oprit devine vizibil în maximum câteva minute dacă `/health` e monitorizat extern.
- Rollback = cod anterior + (la nevoie) restaurare backup; nu se rulează `migrate:rollback` în producție.
- Observabilitate extinsă (Sentry/alerte) rămâne opțională și se adaugă fără schimbări de arhitectură.
