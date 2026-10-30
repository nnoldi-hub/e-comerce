# ADR 0009 – Produse, variante și stoc în admin (6B-2)

Status: acceptat.

- **Agregate.** `products.min_price` și `products.total_stock` erau denormalizate
  fără niciun cod care să le țină la zi. `ProductAggregates::refresh()` le
  recalculează (min preț; suma `stock_on_hand`) și garantează exact o variantă
  implicită. Se apelează din `SaveVariantAction`, `AdjustStockAction` și
  `OrderStockDeducter` (stocul fizic scade la expediere), în aceeași tranzacție.
- **Stocul fizic nu se editează în formular.** Singura cale manuală este
  `AdjustStockAction`: intrare, retur, corecție (cu semn), deteriorat; mereu cu
  rând în ledger (`inventory_movements`, append-only), autor și motiv unde e
  cazul. Nu scade sub 0 și nici sub cantitatea rezervată. Permisiune:
  `inventory.adjust`. Stocul inițial al unei variante noi intră ca „intrare”.
- **Variante.** `SaveVariantAction` blochează rândul produsului și impune:
  produs simplu = o variantă fără opțiuni; produs cu variante = opțiuni
  obligatorii, o valoare per atribut, combinație unică. Opțiunile se scriu în
  `product_attribute_values` cu `product_id` completat.
- **Ștergere.** Produsele se șterg soft (și se pot restaura). O variantă cu
  istoric (mișcări de stoc sau rezervări) nu se șterge; nici prin Super Admin,
  pentru că `delete` trece prin politici (vezi ADR 0008). Forță-ștergerea nu
  este expusă în UI.
- **Activare.** Un produs devine activ doar dacă are variantă; `published_at`
  se completează automat la activare.
- **Slug-uri stabile.** `Product` și `Category` nu mai regenerează slugul la
  redenumire (URL-uri stabile pentru SEO). Un editor de slug cu redirecturi
  (`slug_redirects`) rămâne pentru o etapă ulterioară.
- **Descriere** ca text simplu: storefront-ul trebuie să o escapeze.
- **Imagini** (Spatie Media Library) și atribute la nivel de produs: 6B-3.
