# ADR 0002: Plăți și stoc în ciclul de viață al comenzii

Stare: acceptat. Data: 2026-10-29.

## 1. Stocul fizic se scade la expediere

**Decizie.** Rezervarea ține stocul de la plasarea comenzii până la expediere.
La `shipped`, `TransitionOrderStatusAction` apelează `OrderStockDeducter`: scade
`stock_on_hand` și `stock_reserved` și scrie o mișcare `SALE` în ledger, cu
referință la comandă. Confirmarea plății nu atinge stocul.
**Motive.** Comenzile ramburs nu au moment de plată înainte de expediere; la
rambursare înainte de expediere nu trebuie refăcut niciun stoc.

## 2. Eliberarea rezervărilor are un singur loc

**Decizie.** La `cancelled` și `refunded`, `OrderStockReleaser` eliberează
rezervările active. Utilizarea cuponului se șterge doar la `cancelled` (cuponul redevine
disponibil); la `refunded` rămâne consumată. Expirarea rezervărilor nu mai
eliberează direct: anulează comanda prin `TransitionOrderStatusAction`, deci
intră și în istoric. Releaser-ul este idempotent (nu eliberează de două ori).

## 3. O comandă plătită nu se anulează

**Decizie.** `* -> cancelled` este refuzată cât timp `payment_status = paid`
(`InvalidOrderTransitionException`, motiv `paid_order`). Se rambursează (`-> refunded`).
Tranziția `cancelled -> refunded` rămâne permisă, pentru rambursarea unei plăți
sosite după anulare.

## 4. Plata sosită când comanda nu mai poate fi onorată

**Decizie.** Plata se înregistrează ca `paid` (banii au intrat), comanda rămâne
neschimbată, nu se mișcă stoc, iar `UnexpectedPaymentReceived` cere rambursare
manuală. Motive: `order_closed` (anulată/rambursată), `already_paid` (a doua plată),
`wrong_state`. Motivul este salvat și în `payments.payload.unexpected_reason`.

## 5. Stripe

**Decizie.** `checkout.session.completed` marchează plata doar dacă `payment_status`
este `paid` sau `no_payment_required`; pentru metode asincrone (`unpaid`) plata rămâne
în așteptare până la `checkout.session.async_payment_succeeded`. Sesiunea Checkout
expiră la 31 de minute, iar rezervarea pentru card ține 45, deci o plată nu poate
sosi după anulare din cauza expirării. Cererea de creare a sesiunii poartă antet
`Idempotency-Key` și are timeout (15 s, conectare 5 s), fără reîncercări automate.

## 6. Reguli care rămân

Ordinea blocărilor: comandă, variante (după `id`), rezervări; la plăți: plată, apoi
comandă. Apelurile externe nu se fac sub blocări. Citirile simple vin după blocări.
