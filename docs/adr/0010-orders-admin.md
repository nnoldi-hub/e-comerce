# ADR 0010 – Comenzile în admin (6C)

Status: acceptat.

- **Doar citire.** Comenzile nu se creează și nu se editează din admin; lista
  (căutare după număr/email, filtre după status și plată) și pagina de detaliu
  (articole, totaluri, adrese, plăți, rambursări, expediere, istoric).
- **Acțiuni = apeluri către domeniu, fără reguli noi:**
  - *Schimbă statusul* → `TransitionOrderStatusAction`. Manual doar spre
    `processing`, `completed`, `returned`, `cancelled`; `shipped`, `delivered`,
    `paid` și `refunded` au fluxuri proprii. Anularea unei comenzi plătite nu
    este oferită (se rambursează).
  - *Expediază* → `ShipOrderAction` (curier, AWB, link; scade stocul o dată).
  - *Marchează livrată* → `MarkShipmentDeliveredAction` (la ramburs plata devine
    încasată).
  - *Rambursează* → `RefundPaymentAction` (plafon, idempotență, fără apel extern
    sub lock). O rambursare Stripe rămâne „în curs” până la webhook.
- **Permisiuni:** `orders.view` (vezi), `orders.update-status` (status, expediere,
  livrare), `orders.refund` (rambursare), verificate prin `OrderPolicy` și în
  handler-ul fiecărei acțiuni (nu doar prin vizibilitatea butonului).
- **Erori:** refuzurile de domeniu se arată ca notificare; erorile neașteptate
  sunt raportate (`report()`) și afișate generic. Actorul (`created_by`,
  `changed_by`) este utilizatorul autentificat.
- Docblock-uri `@throws` adăugate pe `TransitionOrderStatusAction::execute` și
  `ShipOrderAction::execute` (fără schimbări de comportament).
