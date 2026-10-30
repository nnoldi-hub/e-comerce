# ADR 0011 – Jurnal de audit și alerte de stoc (6D)

Status: acceptat.

## Jurnal de audit
- Se folosește `spatie/laravel-activitylog` (tabelul există). Ce se urmărește
  este declarat într-un singur loc, `config/audit.php` (model → câmpuri, titlu,
  evenimente), iar `AuditObserver` îl aplică; modelele nu au nevoie de trait.
- Se înregistrează: cine (utilizatorul autentificat), ce înregistrare, ce
  câmpuri s-au schimbat (vechi → nou), plus un titlu care rămâne vizibil și după
  ștergere. Acoperit: produs, variantă, categorie, brand, atribut (și valori),
  cupon, utilizator (nume, email, `is_active`; fără crearea clienților).
- **Nu** intră: stocul fizic (are `inventory_movements`), agregatele produsului
  (scrise prin query builder), parole, date de plată. Comenzile au deja
  `order_status_histories`, plăți/rambursări/expedieri au `created_by`.
- Autentificările personalului (reușite și eșuate, cu IP) se înregistrează în
  logul `auth`; clienții nu.
- `Relation::enforceMorphMap` cere ca fiecare model auditat să aibă alias: s-au
  adăugat `attribute`, `attribute_value`, `coupon`.
- Ecran „Jurnal de audit” (doar citire, permisiunea `audit.view`, implicit doar
  Super Admin). Jurnalul nu se șterge din UI; `activitylog:clean` zilnic respectă
  `delete_records_older_than_days` (365).

## Alerte de stoc redus
- „Sub prag” = `stock_on_hand ≤ low_stock_threshold`, doar pentru variante cu
  `track_inventory`, ale unui produs activ și nesters.
- `LowStockMonitor` deschide o alertă la trecerea sub prag (o singură notificare),
  escaladează o singură dată la stoc 0 și rezolvă alerta când stocul revine
  peste prag. Se apelează din `LowStockObserver` (orice salvare a variantei,
  inclusiv `decrement()` de la expediere), în aceeași tranzacție; `sweep()` rulează
  orar ca plasă de siguranță (`inventory:check-low-stock`).
- Evenimentul `LowStockAlertRaised` pleacă după commit. Un listener scurt
  împarte notificările către utilizatorii activi cu `inventory.alerts.receive`;
  fiecare `LowStockNotification` este în coadă (email + clopoțel Filament), cu
  reîncercări independente (3 încercări, backoff 30/120 s). Erorile din listener
  se raportează și nu strică operația de stoc.
- Tabele noi: `low_stock_alerts`, `notifications`.

## Operațional
- Cu `QUEUE_CONNECTION=database` rulează un worker (`php artisan queue:work`) și
  cron-ul `schedule:run` (Etapa 7); în teste coada este `sync`.
- După deploy se rulează `RolesAndPermissionsSeeder` (idempotent).
