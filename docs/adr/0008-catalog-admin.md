# ADR 0008 – Administrarea catalogului (6B-1)

Status: acceptat.

- **Permisiuni noi:** `brands.manage`, `attributes.manage`, `coupons.manage`.
  Manager Inventar primește brands/attributes (alături de `categories.manage`);
  cupoanele rămân doar la Super Admin. Seeder-ul este idempotent: se rerulează
  la deploy.
- **Politici** pentru Brand, Category, Attribute, AttributeValue, Coupon; Filament
  le aplică automat (listă, creare, editare, ștergere). Ce este în uz nu se
  șterge: brand cu produse, categorie cu copii sau produse, atribut cu valori
  atribuite produselor, cupon cu utilizări. Se dezactivează în schimb.
- **Categorii:** `path` este calea materializată din sluguri
  (`electronice/telefoane`), recalculată automat la salvare, inclusiv pentru
  descendenți. O categorie nu poate fi mutată sub ea însăși (validare în model
  și opțiuni filtrate în formular). `php artisan categories:rebuild-paths`
  repară datele existente.
- **Valori de atribut:** slug unic per atribut (nu global), în acord cu indexul
  unic `[attribute_id, slug]`.
- **Cupoane:** în formular procente și RON; în baza de date puncte de bază și
  unități minore, ca peste tot în proiect. Codul se normalizează la majuscule.
- Notă cunoscută: `Category` își regenerează slugul când se schimbă numele
  (comportament preexistent); URL-urile se schimbă fără redirect. Se tratează
  odată cu `slug_redirects`, la produse.
