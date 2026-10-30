# ADR 0013 – Imaginile produselor (6B-3)

Status: acceptat.

- **Spatie Media Library** (deja în proiect) + pluginul Filament oficial
  `filament/spatie-laravel-media-library-plugin` pentru încărcare și previzualizare.
- **Două colecții pe produs**, ambele pe discul `public`: `thumbnail` (un singur
  fișier, imaginea principală) și `gallery` (cel mult 10, reordonabile).
  Imaginile sunt ale produsului, nu ale variantelor.
- **Restricții în două locuri:** în formular (JPEG/PNG/WebP, 5 MB, doar imagini)
  și în colecție (`acceptsMimeTypes`), ca un upload făcut din cod, nu doar din
  formular, să respecte aceeași regulă. Tipul se verifică după conținut, nu după
  extensie; SVG nu e acceptat (poate conține script).
- **Conversii:** `thumb` (160×160, decupat) se generează imediat, fiindcă îl
  folosește panoul de administrare și nu trebuie să depindă de un worker;
  `card` (480 px lățime) și `large` (1200 px) merg în coadă. Fără worker,
  originalul rămâne disponibil și panoul funcționează; vitrina folosește
  conversiile când există.
- **Ștergere:** produsele se șterg soft, iar fișierele rămân; ele dispar doar la
  ștergerea definitivă.
- **Operare:** `php artisan storage:link` (o dată pe mediu), worker de queue
  pornit în producție, `upload_max_filesize` / `post_max_size` mai mari decât
  5 MB × numărul de imagini încărcate odată.
- **Nu e în această etapă:** imagini pe variantă, text alternativ (alt) per
  imagine, conversii WebP/AVIF, CDN.
