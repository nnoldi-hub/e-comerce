# ADR 0012 – Dashboard și rapoarte (6E)

Status: acceptat.

- **Interogările stau în domeniu**, nu în widget-uri: `SalesReport` (vânzări,
  încasări, vânzări pe zile, comenzi pe status, top produse, comenzi de
  expediat), testat separat de UI. Widget-urile doar îl apelează și formatează.
- **Definiții** (documentate în clasă):
  - *Vânzări* = comenzi confirmate (`paid`, `processing`, `shipped`, `delivered`,
    `completed`) după `placed_at`; anulatele, returnatele, rambursatele și cele
    neplătite nu intră. O comandă ramburs rămâne în afară până trece în
    „în pregătire”.
  - *Încasat* = plăți după `paid_at` (la ramburs: momentul livrării);
    *rambursat* = rambursări reușite după `processed_at`; *net* = diferența.
  - Sume în unități minore, afișate în RON; intervalele în fusul orar al
    aplicației.
- **Dashboard** (descoperit automat din `app/Filament/Widgets`): Vânzări (azi, 30
  de zile, încasat net, de expediat), Stoc, grafic vânzări/comenzi pe zile (7/30/90
  de zile), comenzi pe status și cele mai vândute produse. Vizibilitate pe
  permisiuni (`canView`): vânzările cer `orders.view`, stocul `inventory.view`;
  fiecare rol vede doar ce poate citi. Fără polling (interogările sunt de
  agregare); filtrul graficului vine de la client și este validat.
- **Export CSV al comenzilor** (lista de comenzi, `orders.view`): perioadă cel mult
  366 de zile, fără date personale (nici email, nici adrese), UTF-8 cu BOM, coduri
  de status stabile, celule neutralizate împotriva formulelor, fiecare export în
  jurnalul de audit.
- **Nu e în această etapă:** pagină de rapoarte cu filtre libere de perioadă,
  export pentru alte entități, valută multiplă (magazinul e în RON), cache pentru
  agregări (de adăugat dacă dashboardul devine lent; `orders.placed_at` poate
  primi index).
