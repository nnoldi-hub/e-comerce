# ADR 0003 – Efecte secundare asincrone, plată reluabilă, webhook-uri

Status: acceptat (Etapa 5, Sesiunea B). Completează ADR 0002.

## Context
Emailurile și evenimentele ieșeau din tranzacții, dar rulau sincron, iar un
`OrderStatusChanged` publicat într-o tranzacție ulterior anulată anunța o
schimbare care nu avusese loc. `StartPaymentAction` apela gateway-ul fără
protecție la dublu-click sau la crash. Webhook-urile treceau prin grupul `web`
și nu aveau stare vizibilă pentru erori.

## Decizii
1. **După commit și în coadă.** `OrderStatusChanged` și
   `UnexpectedPaymentReceived` implementează `ShouldDispatchAfterCommit`.
   `SendOrderConfirmation` și `SendOrderStatusNotification` sunt `ShouldQueue`
   cu `$afterCommit`, 3 încercări și backoff 30 s / 120 s. Pe coadă reală
   excepția se propagă (reîncercare); cu driverul `sync` este doar raportată,
   ca emailul să nu strice o comandă deja creată. `InitiateOrderPayment` rămâne
   sincron (clientul are nevoie de redirect imediat), cu try/catch.
2. **`StartPaymentAction` în trei faze.** Lock pe comandă + alegere/creare rând
   de plată (tranzacție scurtă) → apel gateway fără lock → salvare referință
   (tranzacție scurtă). Cheia de idempotență Stripe conține id-ul plății, deci
   dublu-click și reluarea după crash dau aceeași sesiune. O sesiune cu redirect
   mai veche de 25 min (< 31 min Stripe) este înlocuită; cea veche devine
   `failed` cu `superseded`.
3. **„Plătește acum".** `POST /checkout/pay/{order:number}`, rută semnată
   (24 h) + `throttle:10,1`. Linkul se generează pe pagina de succes pentru
   comenzile cu card în `awaiting_payment`.
4. **Webhook-uri separate.** `routes/webhooks.php`, prefix `/webhooks`, doar
   `throttle:webhooks` (600/min pe IP): fără sesiune, cookie-uri sau CSRF.
   Semnătura invalidă se loghează (`warning`) și întoarce 403 JSON.
5. **Stare și erori în `webhook_events`.** Coloane noi `status`
   (`pending`/`processed`/`failed`), `attempts`, `error`.
   - Eroare permanentă (sumă/monedă greșită): `failed`, răspuns 202.
   - Plată negăsită: 409 (providerul reîncearcă) doar în primele 10 min de la
     primul eveniment, apoi `failed` + 202. Motiv: referința se salvează după
     răspunsul providerului, deci webhook-ul poate sosi primul.
   - Reîncercările aceluiași `event_id` sunt deduplicate prin cheia unică.

## Consecințe
- Pe producție `QUEUE_CONNECTION` trebuie să fie o coadă reală
  (`database`/`redis`) cu un worker pornit; altfel emailurile nu pleacă.
- Un eveniment `failed` poate fi reprocesat retrimițându-l din dashboard-ul
  providerului (același `event_id`).
