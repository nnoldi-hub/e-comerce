<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * Modele de pagini legale pentru un magazin online din România. Se creează
 * NEPUBLICATE: completează datele dintre [paranteze], verifică textele cu un
 * jurist și abia apoi publică-le din admin (Conținut → Pagini).
 * Idempotent: nu suprascrie paginile existente.
 */
class LegalPagesSeeder extends Seeder
{
    private const NOTICE = "> **Ciornă – de șters la publicare.** Completează datele dintre [paranteze] și verifică textul cu un jurist înainte să publici pagina.\n\n";

    public function run(): void
    {
        foreach ($this->pages() as $position => $page) {
            ContentPage::firstOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => self::NOTICE.$page['content'],
                    'meta_description' => $page['meta'],
                    'is_published' => false,
                    'show_in_footer' => true,
                    'position' => $position,
                ],
            );
        }
    }

    /** @return list<array{slug: string, title: string, meta: string, content: string}> */
    private function pages(): array
    {
        return [
            [
                'slug' => ContentPage::TERMS,
                'title' => 'Termeni și condiții',
                'meta' => 'Condițiile de utilizare a magazinului online și de plasare a comenzilor.',
                'content' => <<<'MD'
## 1. Despre vânzător

Magazinul online este operat de **[DENUMIRE FIRMĂ]**, cu sediul în [ADRESĂ SEDIU], CUI [CUI], nr. de înregistrare la Registrul Comerțului [J../../....], email [EMAIL CONTACT], telefon [TELEFON].

## 2. Comenzi

Prin plasarea unei comenzi confirmi că ai peste 18 ani și că datele introduse sunt corecte. Contractul se consideră încheiat când primești emailul de confirmare a comenzii. Ne rezervăm dreptul de a anula comenzile cu produse indisponibile sau cu prețuri afișate eronat, caz în care te anunțăm și restituim sumele deja plătite.

## 3. Prețuri și TVA

Prețurile sunt exprimate în lei (RON) și [includ / nu includ] TVA. Valoarea transportului se afișează în coș, înainte de finalizarea comenzii.

## 4. Plata

Poți plăti cu cardul bancar (procesat securizat de partenerul nostru de plăți; nu stocăm datele cardului) sau ramburs la livrare, când această opțiune este disponibilă.

## 5. Livrare

Condițiile de livrare sunt descrise în pagina [Livrare și retur](/pagina/livrare-si-retur).

## 6. Dreptul de retragere

Dacă ești consumator, ai dreptul să te retragi din contract în **14 zile** de la primirea produselor, fără să dai explicații, conform OUG 34/2014. Detalii și procedură: [Livrare și retur](/pagina/livrare-si-retur).

## 7. Conformitate și garanții

Produsele trebuie să corespundă descrierii. În caz de neconformitate, beneficiezi de drepturile prevăzute de legislația privind vânzarea bunurilor de consum. Ne poți scrie la [EMAIL CONTACT] pentru orice reclamație.

## 8. Date personale

Prelucrarea datelor tale este descrisă în [Politica de confidențialitate](/pagina/politica-de-confidentialitate).

## 9. Soluționarea litigiilor

Încercăm să rezolvăm amiabil orice nemulțumire. Ca consumator poți apela și la:

- **ANPC** – [anpc.ro](https://anpc.ro)
- **SAL** (soluționarea alternativă a litigiilor) – [anpc.ro/ce-este-sal](https://anpc.ro/ce-este-sal)
- **SOL** (platforma europeană de soluționare online a litigiilor) – [ec.europa.eu/consumers/odr](https://ec.europa.eu/consumers/odr)

## 10. Legea aplicabilă

Prezentul contract este guvernat de legea română.
MD,
            ],
            [
                'slug' => ContentPage::PRIVACY,
                'title' => 'Politica de confidențialitate',
                'meta' => 'Cum colectăm, folosim și protejăm datele tale personale.',
                'content' => <<<'MD'
## 1. Cine prelucrează datele

Operatorul datelor este **[DENUMIRE FIRMĂ]**, CUI [CUI], [ADRESĂ SEDIU]. Ne poți scrie la [EMAIL CONFIDENȚIALITATE].

## 2. Ce date colectăm

- **Cont:** nume, email, telefon, parolă (stocată criptat).
- **Comenzi:** adresa de livrare și de facturare, produsele comandate, datele firmei (dacă ai comandat ca firmă).
- **Tehnice:** cookie-uri esențiale (sesiune, coș de cumpărături) și date de securitate (adresă IP, jurnale de acces).

Nu stocăm datele cardului bancar: plata este procesată de partenerul nostru de plăți.

## 3. De ce le folosim

| Scop | Temei legal |
| --- | --- |
| Procesarea și livrarea comenzilor, facturare | Executarea contractului; obligații legale (contabile, fiscale) |
| Administrarea contului tău | Executarea contractului |
| Securitatea magazinului și prevenirea fraudei | Interes legitim |
| Emailuri despre comanda ta | Executarea contractului |

## 4. Cui le transmitem

Doar cât este necesar: firmelor de curierat (livrare), procesatorului de plăți, furnizorilor de găzduire și email, contabilului nostru și autorităților, când legea o cere. [COMPLETEAZĂ cu furnizorii tăi reali.]

## 5. Cât timp le păstrăm

Datele comenzilor se păstrează pe durata prevăzută de legislația contabilă și fiscală ([5 / 10] ani). Contul și datele asociate se păstrează până îl ștergi.

## 6. Drepturile tale

Ai dreptul de acces, rectificare, ștergere, restricționare, portabilitate și opoziție. Le exerciți scriind la [EMAIL CONFIDENȚIALITATE]. Dacă nu ești mulțumit, te poți adresa **ANSPDCP** ([dataprotection.ro](https://www.dataprotection.ro)).

## 7. Cookie-uri

Folosim doar cookie-uri strict necesare funcționării magazinului (sesiune, coș, securitate). [Dacă adaugi analiză sau marketing, trebuie să ceri acordul înainte și să actualizezi această secțiune.]
MD,
            ],
            [
                'slug' => 'livrare-si-retur',
                'title' => 'Livrare și retur',
                'meta' => 'Metode și costuri de livrare, termene și procedura de returnare a produselor.',
                'content' => <<<'MD'
## Livrare

Livrăm în România prin curier. Costul transportului se calculează în coș, în funcție de valoarea comenzii: [COMPLETEAZĂ, ex. 19,99 lei pentru comenzi sub 200 lei; gratuit peste].

Comenzile se predau curierului în [1–2] zile lucrătoare de la confirmare, iar livrarea durează de regulă [1–3] zile lucrătoare. Primești email cu numărul AWB când comanda pleacă.

La primire, verifică coletul în fața curierului. Dacă este deteriorat, menționează acest lucru pe documentul de livrare și anunță-ne la [EMAIL CONTACT].

## Dreptul de retragere (retur în 14 zile)

Ca persoană fizică, poți returna produsele în **14 zile** de la primire, fără să dai motive.

1. Anunță-ne la [EMAIL CONTACT], menționând numărul comenzii și produsele returnate.
2. Trimite produsele în ambalajul original, nefolosite, la adresa [ADRESĂ RETUR], în maximum 14 zile de la anunț.
3. Rambursăm sumele (inclusiv transportul standard de la livrare) în cel mult 14 zile de la primirea retururilor, în același mod în care ai plătit.

Costul transportului pentru retur [este suportat de client / este suportat de noi]. Răspunzi doar pentru diminuarea valorii produselor cauzată de manipulare peste cea necesară verificării lor.

**Excepții:** produsele personalizate, cele sigilate care nu pot fi returnate din motive de igienă după desigilare și celelalte situații prevăzute de lege.

## Produse neconforme

Dacă ai primit un produs defect sau diferit de cel comandat, scrie-ne la [EMAIL CONTACT] cu poze; remediem problema prin înlocuire, reparare sau restituire, conform legii.
MD,
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact',
                'meta' => 'Datele de contact ale magazinului.',
                'content' => <<<'MD'
## Scrie-ne

- **Email:** [EMAIL CONTACT]
- **Telefon:** [TELEFON] ([PROGRAM, ex. luni–vineri, 9:00–17:00])

## Date firmă

**[DENUMIRE FIRMĂ]**
CUI: [CUI] · Reg. Com.: [J../../....]
Sediu: [ADRESĂ SEDIU]

La orice mesaj despre o comandă, te rugăm să menționezi numărul comenzii.
MD,
            ],
        ];
    }
}
