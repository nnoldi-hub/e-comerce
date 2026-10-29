# ADR 0005 – Expedieri și livrare

Status: acceptat (Etapa 5, Pachetul D1). Completează ADR 0002–0004.

## Decizii
1. **Tabela `shipments`**: curier (text liber, `orders` nu păstrează metoda de
   livrare aleasă), AWB și link de tracking opționale, status `shipped` /
   `delivered`, datele evenimentelor, autorul. O singură expediere per comandă;
   expedierile parțiale (`fulfillment_status = partial`) nu sunt în scop.
2. **`ShipOrderAction`** este singura cale de expediere: într-o tranzacție
   creează expedierea, mută comanda în `shipped` prin
   `TransitionOrderStatusAction` (singurul loc care scade stocul, o dată) și
   setează `fulfillment_status = fulfilled`. Dacă oricare pas eșuează, nu
   rămâne nimic. Se poate expedia din `paid`, `processing`, sau din `pending`
   la ramburs (trecând întâi prin `processing`).
3. **Un singur email la expediere.** `OrderShipped` (după commit) declanșează
   `SendShipmentNotification` cu curier, AWB și link. Emailul generic de status
   pentru `shipped` este omis doar dacă există o expediere; o tranziție
   `shipped` fără expediere rămâne notificată ca până acum.
4. **Livrarea** (`MarkShipmentDeliveredAction`): expedierea devine `delivered`,
   comanda `delivered`. La ramburs, plata `cod` devine `paid` în acest moment
   (curierul a încasat), ca rambursările ulterioare să poată porni.
5. **Fără interfață încă:** `orders:ship {număr} {curier} --awb= --url=` și
   `orders:deliver {număr}` până la panoul de administrare (Etapa 6).

## Nerezolvat
- Trecerea automată `delivered` → `completed` după o perioadă.
- Retur: tranziția `returned` există, dar refacerea stocului rămâne manuală.
