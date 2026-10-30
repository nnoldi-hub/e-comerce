# Deploy și operare (Linux VPS)

Țintă: Ubuntu 24.04, Nginx, PHP-FPM 8.3, MySQL 8.4, Supervisor, cron. Fișierele de configurare sunt în `deploy/`, scripturile în `scripts/`.

## 1. Pregătirea serverului (o singură dată)

1. Pachete: `nginx php8.3-fpm php8.3-{cli,mysql,mbstring,xml,curl,intl,bcmath,gd,zip} mysql-server supervisor composer git unzip`.
2. `php.ini` (FPM și CLI): `upload_max_filesize=15M`, `post_max_size=64M`, `memory_limit=256M`, `expose_php=Off`, OPcache activ (`opcache.validate_timestamps=0` după ce deploy-ul rulează `optimize`).
3. MySQL: bază și utilizator dedicat, fără drepturi globale:
   `CREATE DATABASE e_comerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
   `CREATE USER 'e_comerce'@'localhost' IDENTIFIED BY '...'; GRANT ALL ON e_comerce.* TO 'e_comerce'@'localhost';`
4. Cod: `git clone` în `/var/www/e-comerce/current`, proprietar `www-data`; `storage/` și `bootstrap/cache/` inscriptibile.
5. `.env`: `cp .env.production.example .env`, completează valorile, apoi `php artisan key:generate`. `chmod 600 .env`.
6. Assets: `npm ci && npm run build` (local sau în CI) — `public/build` nu este în git; urcă-l pe server.
7. Prima instalare: `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (rolurile), `php artisan storage:link`, `php artisan optimize`, creează primul Super Admin (`php artisan tinker` sau seeder-ul tău de admin) și activează MFA.
8. Nginx: `deploy/nginx/e-comerce.conf`, apoi `certbot --nginx`.
9. Worker: `deploy/supervisor/e-comerce-worker.conf` (sau systemd). Cron: `deploy/cron/e-comerce` în `/etc/cron.d/`. Logrotate: `deploy/logrotate/e-comerce`.
10. Webhook Stripe: endpoint `https://<domeniu>/webhooks/stripe` (verifică prefixul în `routes/webhooks.php`), secretul în `STRIPE_WEBHOOK_SECRET`.

## 2. Verificare

- `php artisan ops:check --production` — ieșire non-zero dacă ceva e în eșec (mediu, debug, HTTPS, mailer, coadă, cron, Stripe, disc).
- Monitorizare externă (UptimeRobot etc.): `GET /up` (simplu) și `GET /health` cu `Authorization: Bearer $HEALTH_TOKEN` (503 la eșec: bază, cache, coadă blocată, cron oprit, disc plin). Fără `HEALTH_TOKEN`, `/health` răspunde 404.
- Bătaia de inimă a planificatorului se scrie în fiecare minut; dacă `/health` raportează `scheduler`, cron-ul nu rulează.

## 3. Deploy

`scripts/deploy.sh [ref]`: mentenanță → git → `composer install --no-dev` → backup DB → `migrate --force` → `optimize` → `queue:restart` → `ops:check --production`. Aplicația iese din mentenanță și dacă un pas eșuează (rămâne însă pe codul nou: vezi rollback).

## 4. Rollback

1. `git checkout $(cat storage/app/.previous-release)`; `composer install --no-dev -o`.
2. Migrările sunt aditive (nu rulăm `migrate:rollback` în producție). Dacă o migrare a stricat date: restaurează backup-ul de dinainte de deploy:
   `gunzip -c /var/backups/e-comerce/<fisier>.sql.gz | mysql -u... -p e_comerce`
3. `php artisan optimize && php artisan queue:restart`, apoi `ops:check --production`.

## 5. Backup

`scripts/backup-db.sh` (cron zilnic 03:10, retenție 14 zile, verifică integritatea arhivei). Fișierele încărcate (`storage/app/public`) se copiază separat (rsync/snapshot VPS). Un backup netestat nu e backup: restaurează lunar pe o bază de test.

## 6. Cache, sesiuni, coadă

Implicit `database` (suficient la început). La trafic mare: Redis pentru `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` (`REDIS_*` sunt în șablon). Schimbarea cozii cere repornirea workerilor.

## 7. În spatele unui load balancer / Cloudflare

Setează `TRUSTED_PROXIES` (IP-uri/CIDR separate prin virgulă, sau `*` doar dacă aplicația nu este accesibilă direct). Fără asta, `request()->ip()` ar fi IP-ul proxy-ului (limitele de rată ar lovi toți utilizatorii deodată) și HSTS/URL-urile semnate ar greși schema.

## 8. Lista de lansare

- [ ] `ops:check --production` fără FAIL (și fără WARN neînțeles)
- [ ] HTTPS valid, redirect 80→443, HSTS prezent în răspuns
- [ ] `.env` cu `chmod 600`, în afara git; `APP_DEBUG=false`
- [ ] Mailer real testat (comandă de test + resetare parolă)
- [ ] Stripe în mod live, webhook testat cu un plată reală de valoare mică + rambursare
- [ ] Super Admin cu MFA; conturile de test șterse
- [ ] Backup rulat și restaurat o dată pe o bază de test
- [ ] Monitorizare externă pe `/up` și `/health`
- [ ] Worker și cron confirmate (`/health`: queue și scheduler `ok`)
