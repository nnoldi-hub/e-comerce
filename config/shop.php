<?php

return [

    /*
    | Cât timp rămâne rezervat stocul unei comenzi neplătite, în funcție de
    | metoda de plată. După expirare, rezervarea este eliberată automat.
    */
    'reservation_minutes' => [
        'card' => 30,
        'cod' => 1440,
    ],

];
