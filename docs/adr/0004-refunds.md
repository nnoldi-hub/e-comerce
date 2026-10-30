# ADR 0004 – Rambursări

Status: acceptat (Etapa 5, Pachetul C). Completează ADR 0002 și 0003.

## Decizii
1. **Tabelă `refunds`** (plată, comandă, sumă, motiv, status `pending` /
   `succeeded` / `failed`, referință la procesator, autor). O plată poate avea
   mai multe rambursări parțiale.
2. **Plafon.** Suma rambursărilor `pending` + `succeeded` nu depășește suma
   plății; verificarea se face sub lock pe rândul plății. Una `failed` nu
   consumă din plafon.
3. **Trei faze**, ca la plată: rezervare sub lock → apel gateway fără lock →
   salvare rezultat. Cheia de idempotență este `ecom-refund-{id rambursare}`.
4. **Efecte la succes.** Plata și `orders.payment_status` devin
   `partially_refunded` sau `refunded`. La rambursare integrală comanda trece în
   `refunded` (eliberează rezervările) doar dacă tranziția e permisă; din
   `shipped` nu este, deci rămâne la decizia administratorului (retur).
5. **Anulare.** O comandă `paid` sau `partially_refunded` nu se anulează.
6. **Gateway-uri.** Manual și COD înregistrează rambursarea ca reușită
   (banii se mută în afara sistemului). Stripe rambursează prin PaymentIntent-ul
   sesiunii Checkout; `pending` rămâne `pending` local.
7. **Fără interfață încă.** `php artisan orders:refund {număr} {sumă?}
   --reason=` până la panoul de administrare (Etapa 6), care va aplica
   permisiunile.

## Nerezolvat (pachetul D)
- Rambursări inițiate din dashboard-ul Stripe sau finalizate asincron
  (`charge.refunded`, `refund.updated`): momentan nu se reflectă automat.
- O rambursare rămasă `pending` după un crash nu are încă reconciliere.
