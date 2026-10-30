# ADR 0019 — Conținut, adrese și redirecturi în vitrină

Data: 2026-10-30 · Status: acceptat

## Context

După vitrina de bază (ADR 0018), un magazin real are nevoie de pagini legale administrabile, de un cont mai util,
de totaluri clare pe comenzi și de adrese stabile în Google. Fiecare dintre ele are o alegere cu consecințe.

## Decizii

1. **Pagini de conținut în Markdown, fără HTML.** Textul se scrie în admin ca Markdown și se randează cu
   `html_input=strip` și `allow_unsafe_links=false`: un editor compromis sau o greșeală nu poate introduce scripturi.
   Paginile legale vin ca șabloane **nepublicate** (`LegalPagesSeeder`), ca să nu ajungă online texte necompletate.
2. **Totaluri calculate într-un singur loc** (`OrderTotals`). Pagina de succes, emailurile și contul folosesc aceleași
   rânduri. TVA-ul este „inclus” doar când subtotal − reducere + transport = total; altfel este linie adăugată.
   Adresele se formatează tot într-un singur loc (`AddressFormatter`).
3. **Comanda păstrează copia adresei.** Carnetul de adrese (`addresses`) precompletează checkout-ul, dar comanda
   stochează propriul JSON: modificarea sau ștergerea unei adrese nu schimbă comenzile vechi.
4. **Comenzile de oaspete se leagă de cont doar după confirmarea emailului** (evenimentele `Verified` și `Login` pentru
   conturi confirmate). Altfel, cineva ar putea „revendica” comenzile unei adrese pe care nu o deține. Legarea intră în
   jurnalul de audit.
5. **Slug stabil, redirecturi permanente.** Redenumirea unui produs sau a unei categorii nu schimbă URL-ul; schimbarea
   explicită a slug-ului sau mutarea unei categorii reține adresa veche. Redirecturile rețin ținta (id), nu adresa nouă,
   deci lanțurile de redenumiri duc la adresa curentă. O adresă preluată de un element viu nu se mai redirecționează.
6. **Administrarea livrării și taxelor cere `settings.manage`.** Tarifele trebuie să acopere continuu de la 0 și să
   se termine fără limită superioară (altfel anumite coșuri n-ar avea transport). Ultima metodă de livrare activă și
   ultima cotă de TVA activă dintr-o țară nu se pot dezactiva.

## Consecințe

- Textele legale rămân responsabilitatea operatorului: șabloanele trebuie completate și verificate de un jurist.
- Schimbarea unui slug în masă (import) nu trece prin formular, dar trece prin model, deci redirecturile se rețin la fel.
- Redirecturile se acumulează; nu există încă o curățare automată (se poate adăuga dacă tabelul crește).
