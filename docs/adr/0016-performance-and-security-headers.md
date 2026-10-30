# ADR 0016 – Performanță și headere de securitate (7A, 7B-1)

Status: acceptat.

## Performanță
- **Indexuri doar pentru interogări care există** (nu speculative): `orders(status,
  placed_at)` și `orders(placed_at)` pentru vânzări, grafic și export;
  `payments(status, paid_at)` pentru încasat; `refunds(status, processed_at)` pentru
  rambursat. Migrarea verifică `Schema::hasIndex`, deci e idempotentă. Coloanele
  de join (`order_items.order_id`, `orders.user_id`, `shipments.order_id`) au deja
  index din cheile străine.
- **Fără cache pe agregările din dashboard, deliberat.** Cu indexurile de mai sus,
  interogările sunt pe intervale indexate; un cache ar adăuga date învechite și
  invalidare fără o problemă măsurată. Se reconsideră când un widget depășește
  cca. 200 ms pe date reale; locul potrivit este `SalesReport`, nu widget-urile.
- **Gardă N+1 în teste:** `QueryCountTest` compară numărul de interogări ale
  listelor din admin cu puține și cu multe rânduri. O listă nouă sau o coloană
  nouă care introduce lazy loading pică testul, nu producția.

## Headere de securitate
- `SecurityHeaders` este middleware **global** (înregistrat în `AppServiceProvider`
  prin kernel), nu în grupul `web`: panoul Filament nu trece prin acel grup.
- Trimite `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`,
  `Permissions-Policy` și `Cross-Origin-Opener-Policy`. Nu suprascrie un header
  setat explicit de o rută.
- **HSTS** doar pe HTTPS în producție (necesită proxy de încredere configurat
  corect ca `isSecure()` să fie adevărat în spatele Nginx; vezi 7C).
- **CSP lipsește intenționat în această etapă:** Filament/Livewire cer Alpine cu
  expresii evaluate. Se adaugă pentru vitrină, întâi `Content-Security-Policy-Report-Only`,
  când există paginile ei (Etapa 8).
