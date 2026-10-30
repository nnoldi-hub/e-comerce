# Progres E-commerce Laravel

Proiect: E-commerce pe Laravel 13
Creat: 2026-10-27
Ultima actualizare: 2026-10-29

## Status
- [x] Etapa 1: Arhitectură, ERD și configurare inițială (completă)
- [x] Etapa 2: Autentificare, roluri și securitate (ACL) (completă)
- [x] Etapa 3: Catalogul de produse (management complex) (completă)
- [/] Etapa 4: Coșul de cumpărături și motorul de checkout
- [x] Etapa 5: Gestiunea comenzilor și integrarea plăților (completă)
- [/] Etapa 6: Panoul de administrare profesional
- [/] Etapa 7: Optimizări, testare și lansare

## Progres detaliat

### Etapa 1: Arhitectură, ERD și configurare inițială
- [x] Repository Git inițializat
- [x] Mediu local reproductibil (Docker/Sail)
- [x] ERD complet validat
- [x] Scheletul modular app/Domain/*
- [x] Configurare pachete (permission, activitylog, medialibrary, horizon, scout)
- [x] Pipeline CI (pint, larastan, pest)
- [x] AppServiceProvider cu restricții stricte
- [x] Configurare inițială finalizată

### Etapa 2: Autentificare, roluri și securitate (ACL)
- [x] Setup Laravel Breeze/Jetstream/Filament auth sau custom
- [x] Spatie permission integrat
- [x] Roluri și permisiuni definite
- [x] Middleware ACL
- [x] Verificare email
- [x] 2FA/Passkeys parțial configurate

### Etapa 3: Catalogul de produse (management complex)
- [x] Migrations: brands, categories, products, product_variants, attributes, attribute_values
- [x] Relații many-to-many și pivot-uri
- [x] Slug redirects
- [x] Media library configurat

### Etapa 4: Coșul de cumpărături și motorul de checkout
- [x] Migrations: carts, cart_items, coupons, coupon_usages
- [x] Modele Cart/CartItem/Coupon
- [x] Structură modulară Domain/Cart, Domain/Checkout creată
- [x] CartService (getCurrentCart, addItem, updateItem, removeItem, clear, subtotal, merge session->user)
- [x] CartController + rute cart (index/add/update/remove)
- [x] CheckoutController + rute checkout (index/store/success)
- [x] CheckoutService (placeOrder cu creare Order/OrderItem/Payment, calcul total)
- [x] Servicii/Actions pentru coș (adăugare/actualizare/eliminare)
- [x] Sesiune/autentificat sync coș (merge implementat)
- [x] Voucher/coupon logic
- [x] Checkout flow (adrese, livrare, metodă plată)
- [x] Validări și calcul prețuri (taxe/reduceri)

### Etapa 5: Gestiunea comenzilor și integrarea plăților
- [x] Migrations: orders, order_items, payments
- [x] Modele Order/OrderItem/Payment completate
- [x] Workflow statusuri cu tranziții validate (`OrderStatus::allowedTransitions`), acțiune unică `TransitionOrderStatusAction` și eveniment `OrderStatusChanged`
- [x] Istoric statusuri în `order_status_histories` (status anterior/nou, comentariu, utilizator)
- [x] Coloanele de status mutate din `enum` MySQL în `varchar` (audit ERD #3) + `payment_method` pe comenzi
- [x] Abordare gateway plăți: contract `PaymentGateway`, `PaymentGatewayManager`, implementări manual/COD/Stripe Checkout
- [x] Inițiere automată plată la plasarea comenzii și redirect către gateway când există URL
- [x] Webhook plăți cu verificare semnătură Stripe, rute fără CSRF și deduplicare în `webhook_events`
- [x] Confirmare plată idempotentă, verificare sumă/monedă, actualizare status comandă
- [x] Conversie rezervări de stoc în vânzări la plată: scade `stock_on_hand`/`stock_reserved` și scrie mișcare `SALE` în ledger
- [x] Email notificări: confirmare comandă și schimbări de status (șabloane HTML)
- [x] Teste: workflow statusuri, plăți manual/COD/Stripe, webhook-uri, emailuri
- [x] Expediere: tabela `shipments`, `ShipOrderAction` (stocul scade la expediere), livrare, email cu AWB, comenzi artisan
- [x] Rambursări: tabela `refunds`, `RefundPaymentAction` cu plafon, rambursări parțiale/integrale, webhook-uri `refund.*` și reconciliere orară

### Etapa 6: Panoul de administrare profesional
- [x] 6A Panou Filament 5 la `/admin`: acces doar pentru personal activ cu `access-admin`, 2FA TOTP obligatoriu cu coduri de recuperare, login separat personal/magazin
- [x] 6B Catalog: mărci, categorii (cale materializată), atribute, cupoane, produse cu variante, ajustări de stoc
- [x] 6C Comenzi: vizualizare, schimbare status, expediere, livrare, rambursare (doar prin acțiunile din domeniu)
- [x] 6D Jurnal de audit și alerte de stoc scăzut (coadă, notificări în panou și email)
- [x] 6E Dashboard, raport de vânzări și export CSV al comenzilor
- [ ] 6B-3 Imagini produs (galerie, thumbnail)
- [ ] Clienți în admin, editor de slug cu redirecturi, gestionare utilizatori/roluri

### Etapa 7: Optimizări, testare și lansare
- [/] Cache, optimizări
- [/] Teste
- [/] Deploy prep

## Log activitate
- 2026-10-28: Verificare stare proiect - progres.md citit, migrations pentru Cart/Orders/Payments existente. Se continuă Etapa 4.
- 2026-10-28: Creată structură Domain/Cart și Domain/Checkout; adăugat CartService (cu merge sesiune->utilizator autentificat), CartController + rute; CheckoutController + rute; CheckoutService pentru creare Order/OrderItem/Payment; completate modele Order/OrderItem/Payment. Updated progres.md.
- 2026-10-29: Finalizată Etapa 5. Adăugat workflow de statusuri cu tranziții validate și istoric (`order_status_histories`), coloane status pe varchar + `orders.payment_method`. Implementat domeniul `Domain/Payments`: contract gateway, manager, gateway-uri manual/COD/Stripe Checkout, inițiere plată la `OrderPlaced`, webhook semnat cu deduplicare (`webhook_events`) și confirmare idempotentă care transformă rezervările în vânzări (`inventory_movements` SALE). Adăugat emailuri de confirmare și schimbare status. Teste: 96 pass, Pint și PHPStan level 6 pass. Migrări rulate.
- 2026-10-30: Etapa 6 (6A-6E) în branch-ul `feat/stage-6-admin`: panou Filament cu 2FA obligatoriu, catalog, comenzi, audit + alerte de stoc, dashboard și export CSV. 291 de teste, Pint și PHPStan level 6 trec. Urmează imaginile (6B-3).
