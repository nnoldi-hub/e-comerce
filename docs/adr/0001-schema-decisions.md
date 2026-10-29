# ADR 0001: Decizii de schemă și de concurență

Stare: acceptat. Data: 2026-10-29.

## 1. Bani ca întregi în unități minore

**Context.** Calculele cu `float` produc diferențe de rotunjire.
**Decizie.** Orice sumă este `bigint unsigned` în bani, plus o monedă `char(3)`.
În cod se folosește `App\ValueObjects\Money` (imutabil) și `MoneyCast`. Cotele de
taxă se stochează în puncte de bază (2100 = 21%). Rotunjirea este half-up.
**Consecințe.** Nu există `decimal`/`float` în calcule. Afișarea împarte la 100 doar la margine.

## 2. Orice produs are cel puțin o variantă

**Decizie.** Coșul, comenzile, stocul și rezervările referă doar `product_variants`.
Un produs simplu are o singură variantă.
**Consecințe.** Nu există ramuri speciale pentru produse fără variante.

## 3. Ledger de stoc doar pentru `stock_on_hand`

**Context.** O rezervare schimbă `stock_reserved`, nu cantitatea fizică.
**Decizie.** `inventory_movements` (append-only) înregistrează doar modificările
lui `stock_on_hand` (achiziție, vânzare, ajustare, retur, deteriorare). Rezervările
trăiesc în `stock_reservations`, iar `product_variants.stock_reserved` este valoarea
denormalizată. `SALE` se scrie la confirmarea plății.
**Consecințe.** `SUM(ledger.quantity) = stock_on_hand` rămâne verificabil
(reconciliere nocturnă). Disponibil = `stock_on_hand - stock_reserved`.

## 4. Comenzile păstrează snapshot-uri

**Decizie.** `order_items` copiază nume, SKU, preț unitar și cota de taxă;
adresele de facturare și livrare sunt JSON pe comandă.
**Consecințe.** Modificările ulterioare din catalog nu schimbă comenzile vechi.
`order_items.product_variant_id` este nullable (`ON DELETE SET NULL`).

## 5. Identificatori

**Decizie.** Cheie primară `bigint` internă. `ulid` public pe produse și coșuri.
Numărul comenzii (`AAAA-000123`) se derivă din `id`, deci este unic fără cursă.

## 6. Idempotența comenzilor

**Decizie.** `orders.idempotency_key` este unic. Blocarea liniilor coșului serializează
dublul-click pe același coș; indexul unic oprește două coșuri cu aceeași cheie. O cheie
refolosită cu alt email este respinsă.
**Nu se folosește** `SELECT ... FOR UPDATE` pe o cheie unică inexistentă: în InnoDB ia
gap lock și produce deadlock între comenzi concurente.

## 7. Ordinea blocărilor la plasarea comenzii

**Decizie.** Ordine fixă: liniile coșului, apoi variantele (după `id`), apoi cuponul.
Toate blocările se iau înaintea primei citiri simple din tranzacție.
**Motiv.** O citire simplă creează snapshot-ul REPEATABLE READ; una făcută înaintea
blocărilor ar vedea stoc sau utilizări de cupon depășite. Ordinea fixă evită deadlock-urile.
Tranzacția se reîncearcă la deadlock (3 încercări).

## 8. Statusuri

**Decizie.** Enum-uri PHP backed (`OrderStatus`, `PaymentStatus`, `StockMovementType`).
Unele coloane sunt `enum` în baza de date (vezi audit, punctul 3), compromis acceptat
până la Etapa 5.

## 9. Relații polimorfice

**Decizie.** `Relation::enforceMorphMap` impus; în baza de date se salvează aliasuri,
nu nume de clase.

## 10. Teste pe aceeași tehnologie ca producția

**Decizie.** Testele rulează pe MySQL (`e_comerce_test`), nu pe SQLite, pentru că
`lockForUpdate()` și nivelul de izolare diferă. Clasa de bază refuză să pornească pe o
bază al cărei nume nu se termină în `_test`. Testul de concurență pornește procese PHP
separate și folosește date confirmate (fără tranzacție învelitoare).
