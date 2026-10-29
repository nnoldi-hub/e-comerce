# ADR 0006 – Rambursări asincrone și reconciliere

Status: acceptat (Etapa 5, Pachetul D2). Completează ADR 0004.

## Decizii
1. **Legătura cu plata.** Rambursările Stripe vorbesc despre PaymentIntent, nu
   despre sesiunea Checkout. La confirmarea plății se salvează
   `payments.provider_intent` (coloană indexată). Plățile confirmate înainte de
   coloană se găsesc și după PaymentIntent-ul din payload-ul evenimentului.
2. **Evenimente.** `refund.created`, `refund.updated`, `refund.failed`.
   `charge.refunded` este ignorat: agregat, fără detaliile rambursării.
   Parsarea produce `RefundNotification`; `parseWebhook` întoarce acum
   `PaymentNotification|RefundNotification|null`.
3. **Rambursări ale noastre vs. din panoul Stripe.** Cererea noastră poartă
   `metadata[idempotency_key] = ecom-refund-{id}`; webhook-ul recunoaște rândul
   local după referință sau după această cheie (acoperă cursa în care webhook-ul
   sosește înaintea salvării referinței). Fără potrivire, rambursarea este
   înregistrată ca rambursare externă (`created_by` null), fără plafon: l-a
   verificat deja Stripe.
4. **`RefundSettlement`** este singurul loc care aplică rezultatul (plată,
   comandă, status comandă), folosit de acțiunea inițială, webhook și
   reconciliere. O rambursare încheiată nu se redeschide.
5. **Reconciliere** (`payments:reconcile-refunds`, implicit pentru rambursări
   `pending` mai vechi de 30 min): cu referință, se citește starea de la
   procesator; fără referință, se repetă apelul cu aceeași cheie de
   idempotență. Fără referință și mai vechi de 23 h (cheile Stripe expiră la
   24 h), rambursarea este marcată `failed` cu mențiunea „verifică manual".
6. **Planificare.** Comanda se rulează orar (vezi `routes/console.php`):
   `Schedule::command('payments:reconcile-refunds')->hourly()->withoutOverlapping();`
