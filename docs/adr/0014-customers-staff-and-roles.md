# ADR 0014 – Clienți, personal și roluri în admin (6F)

Status: acceptat.

- **Un singur model, trei resurse.** `CustomerResource` și `StaffResource` folosesc
  ambele `User`, cu `slug` explicit (`customers`, `staff`). Separarea se face prin
  interogare, nu prin tabel: personal = utilizatori cu cel puțin un rol diferit de
  `Client`; clienți = toți ceilalți (inclusiv cei fără rol, înregistrați din vitrină).
  Rutele de editare/vizualizare folosesc aceeași interogare, deci un client nu se
  poate deschide ca personal și invers (404).
- **Permisiuni în resurse, nu într-un `UserPolicy`:** o singură politică pe `User` ar
  amesteca regulile pentru clienți (`customers.*`) și personal (`users.manage`).
  `RoleResource` cere `roles.manage`.
- **Clienți: doar citire + activare/dezactivare** (`customers.update`). Fără editarea
  emailului sau a parolei din admin. Numărul de comenzi și totalul cheltuit se
  calculează prin subinterogări în aceeași interogare (fără N+1) și numără doar
  comenzile confirmate, cu aceeași definiție ca raportul de vânzări. Comenzile
  clientului se văd doar cu `orders.view`. Dezactivarea oprește conectările noi;
  sesiunile deja deschise expiră normal.
- **Personal: singurul punct de scriere este `StaffAccounts`.** Reguli: doar
  `users.manage`; rolul `Client` nu se atribuie de aici; cel puțin un rol; doar un
  Super Admin atribuie sau modifică Super Admin; nimeni nu se dezactivează și nu
  își schimbă rolurile; rămâne mereu cel puțin un Super Admin activ. Conturile nu se
  șterg (istoricul din audit rămâne atribuibil), doar se dezactivează. Orice
  creare, modificare și resetare de 2FA intră în jurnalul de audit (`staff`).
- **Resetarea 2FA** șterge secretul și codurile de recuperare; la următoarea
  conectare, 2FA fiind obligatoriu, utilizatorul îl configurează din nou. Nu poți
  reseta propriul 2FA din listă; un non-Super Admin nu poate reseta 2FA unui Super Admin.
- **Roluri: doar citire.** Rolurile și permisiunile se definesc în
  `RolesAndPermissionsSeeder`, versionat și testat. Editarea lor în panou ar ocoli
  revizuirea codului.
- **Limba panoului:** `Filament::serving()` setează limba la `app.admin_locale`
  (implicit `ro`) doar pentru cererile panoului; vitrina își păstrează limba
  aplicației. Mesajele de validare Laravel rămân în engleză până se adaugă
  fișierele de traducere `lang/ro`.
- **Imagini:** discul `public` generează URL-uri relative (`/storage/...`), ca imaginile
  să se încarce de la aceeași origine ca pagina, oricare ar fi hostul.
- **Produse:** la creare, statusul poate fi doar `draft` (un produs nou nu are
  variante); activarea rămâne validată la editare.
- **Nu e în această etapă:** editarea clienților, adrese salvate, invitații pe
  email pentru personal, forțarea schimbării parolei, închiderea sesiunilor la
  dezactivare, editor de roluri.
