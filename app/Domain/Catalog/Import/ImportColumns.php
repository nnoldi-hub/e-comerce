<?php

namespace App\Domain\Catalog\Import;

use Illuminate\Support\Str;

/**
 * Coloanele fișierului de import: sursa unică pentru șablon, instrucțiuni și citire.
 * Un rând = o variantă. Rândurile cu același „Cod produs” aparțin aceluiași produs.
 */
final class ImportColumns
{
    /**
     * @var array<string, array{label: string, required: string, help: string, aliases: list<string>}>
     */
    public const COLUMNS = [
        'cod_produs' => [
            'label' => 'Cod produs',
            'required' => 'Doar la produse cu variante',
            'help' => 'Leagă variantele aceluiași produs: toate rândurile cu același cod formează un singur produs. Gol = rândul este un produs simplu de sine stătător.',
            'aliases' => ['cod', 'grup', 'cod_grup'],
        ],
        'nume' => [
            'label' => 'Nume',
            'required' => 'Da (produse noi)',
            'help' => 'Numele produsului, max. 255 de caractere. Se completează o singură dată per produs (pe primul rând).',
            'aliases' => ['nume_produs', 'denumire', 'titlu'],
        ],
        'descriere' => [
            'label' => 'Descriere',
            'required' => 'Nu',
            'help' => 'Text simplu (HTML-ul se elimină).',
            'aliases' => [],
        ],
        'brand' => [
            'label' => 'Brand',
            'required' => 'Nu',
            'help' => 'Numele unui brand care există deja (vezi foaia „Referințe”).',
            'aliases' => ['marca', 'producator'],
        ],
        'categorii' => [
            'label' => 'Categorii',
            'required' => 'Nu',
            'help' => 'Una sau mai multe categorii existente, separate prin „|”. Poți scrie numele (ex. Prize) sau calea (ex. Electrice > Prize).',
            'aliases' => ['categorie'],
        ],
        'status' => [
            'label' => 'Status',
            'required' => 'Nu',
            'help' => 'ciornă (implicit), activ sau arhivat. Un produs „activ” apare imediat în magazin.',
            'aliases' => [],
        ],
        'meta_titlu' => [
            'label' => 'Titlu SEO',
            'required' => 'Nu',
            'help' => 'Titlul pentru Google, max. 255 de caractere. Gol = se folosește numele produsului.',
            'aliases' => ['titlu_seo', 'titlu_meta'],
        ],
        'meta_descriere' => [
            'label' => 'Descriere SEO',
            'required' => 'Nu',
            'help' => 'Descrierea pentru Google, max. 500 de caractere.',
            'aliases' => ['descriere_seo', 'descriere_meta'],
        ],
        'sku' => [
            'label' => 'SKU',
            'required' => 'Da',
            'help' => 'Codul unic al variantei (max. 100 de caractere). Nu se poate repeta în magazin.',
            'aliases' => ['cod_sku', 'cod_intern'],
        ],
        'cod_bare' => [
            'label' => 'Cod de bare',
            'required' => 'Nu',
            'help' => 'EAN / cod de bare.',
            'aliases' => ['ean', 'barcode'],
        ],
        'optiuni' => [
            'label' => 'Opțiuni',
            'required' => 'La produse cu variante',
            'help' => 'Atribut=Valoare, separate prin „;” (ex. Culoare=Roșu; Mărime=M). Atributele și valorile trebuie să existe deja (vezi foaia „Referințe”).',
            'aliases' => ['optiune', 'variante', 'atribute'],
        ],
        'specificatii' => [
            'label' => 'Specificații',
            'required' => 'Nu',
            'help' => 'Caracteristici tehnice, Atribut=Valoare, separate prin „;” (ex. Tensiune=230 V; Curent=16 A). Atributul trebuie să existe deja (Admin → Atribute); valoarea se scrie liber și se creează dacă nu există. La actualizare se modifică doar atributele din fișier. Se completează o singură dată per produs.',
            'aliases' => ['specificatii_tehnice', 'caracteristici', 'specs'],
        ],
        'pret' => [
            'label' => 'Preț',
            'required' => 'Da (produse noi)',
            'help' => 'Prețul de vânzare, în RON, exact cum îl vede clientul (ex. 25,50).',
            'aliases' => ['pret_ron', 'pret_vanzare', 'pret_de_vanzare'],
        ],
        'pret_barat' => [
            'label' => 'Preț barat',
            'required' => 'Nu',
            'help' => 'Prețul vechi, în RON; trebuie să fie mai mare decât Prețul. Apare ca reducere.',
            'aliases' => ['pret_vechi', 'pret_initial'],
        ],
        'cost' => [
            'label' => 'Cost',
            'required' => 'Nu',
            'help' => 'Costul de achiziție, în RON (nu se vede în magazin).',
            'aliases' => ['pret_achizitie', 'cost_achizitie'],
        ],
        'greutate_g' => [
            'label' => 'Greutate (g)',
            'required' => 'Nu',
            'help' => 'Greutatea în grame, număr întreg.',
            'aliases' => ['greutate', 'greutate_grame'],
        ],
        'stoc' => [
            'label' => 'Stoc',
            'required' => 'Nu',
            'help' => 'Cantitatea pe stoc. La produse noi intră în ledger ca „stoc inițial”; la actualizare se face o corecție de inventar până la valoarea din fișier.',
            'aliases' => ['stoc_initial', 'cantitate'],
        ],
        'prag_stoc' => [
            'label' => 'Prag stoc redus',
            'required' => 'Nu',
            'help' => 'Sub această cantitate apare alerta de stoc redus (implicit 5).',
            'aliases' => ['prag', 'prag_stoc_redus'],
        ],
        'urmareste_stoc' => [
            'label' => 'Urmărește stocul',
            'required' => 'Nu',
            'help' => 'da (implicit) sau nu.',
            'aliases' => ['gestionare_stoc'],
        ],
        'precomanda' => [
            'label' => 'Permite precomandă',
            'required' => 'Nu',
            'help' => 'da sau nu (implicit nu): se poate comanda și fără stoc.',
            'aliases' => ['permite_precomanda', 'backorder'],
        ],
        'imagine' => [
            'label' => 'Imagine principală',
            'required' => 'Nu',
            'help' => 'Adresa (URL) publică a imaginii, JPG/PNG/WebP, max. 5 MB. Se descarcă doar la produse noi.',
            'aliases' => ['imagine_principala', 'poza', 'url_imagine'],
        ],
        'galerie' => [
            'label' => 'Galerie',
            'required' => 'Nu',
            'help' => 'Până la 10 adrese de imagini, separate prin „|”. Se descarcă doar la produse noi.',
            'aliases' => ['imagini', 'poze'],
        ],
    ];

    /** Normalizează un antet: fără diacritice, litere mici, cuvinte legate prin „_”. */
    public static function normalize(string $header): string
    {
        $ascii = Str::lower(Str::ascii($header));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');
    }

    /** Cheia coloanei pentru un antet din fișier sau null dacă nu îl recunoaștem. */
    public static function match(string $header): ?string
    {
        $normalized = self::normalize($header);

        if ($normalized === '') {
            return null;
        }

        foreach (self::COLUMNS as $key => $column) {
            if ($normalized === $key || $normalized === self::normalize($column['label'])) {
                return $key;
            }

            foreach ($column['aliases'] as $alias) {
                if ($normalized === $alias) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function labels(): array
    {
        return array_values(array_map(fn (array $column): string => $column['label'], self::COLUMNS));
    }
}
