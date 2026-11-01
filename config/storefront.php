<?php

return [
    /*
     * Bara de beneficii (header și pagina principală). Textele sunt promisiuni
     * către clienți: confirmă-le înainte de lansare și editează-le aici.
     * `page` = slug-ul unei pagini de conținut; linkul apare doar dacă pagina e publicată.
     */
    'benefits' => [
        [
            'icon' => 'truck',
            'short' => 'Livrare rapidă',
            'title' => 'Livrare rapidă',
            'text' => 'Comenzile se pregătesc și pleacă la curier cât mai repede.',
            'page' => 'livrare-si-retur',
        ],
        [
            'icon' => 'return',
            'short' => 'Retur 14 zile',
            'title' => 'Retur în 14 zile',
            'text' => 'Poți returna produsele în 14 zile, conform legii.',
            'page' => 'livrare-si-retur',
        ],
        [
            'icon' => 'shield',
            'short' => 'Garanție produse',
            'title' => 'Garanție',
            'text' => 'Produsele beneficiază de garanție conform legii.',
            'page' => null,
        ],
        [
            'icon' => 'card',
            'short' => 'Plată securizată',
            'title' => 'Plată securizată',
            'text' => 'Plătești cu cardul online sau ramburs, la livrare.',
            'page' => null,
        ],
    ],

    // Un produs este „Nou” în primele N zile după publicare.
    'new_days' => 14,

    // Prag implicit pentru „Stoc redus”, când varianta nu are propriul prag.
    'low_stock' => 5,
];
