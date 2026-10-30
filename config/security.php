<?php

return [

    /*
    | Proxy-uri de încredere. Gol = niciunul (Nginx + PHP-FPM pe aceeași mașină
    | nu are nevoie: Nginx trimite `HTTPS=on` prin fastcgi). Se completează doar
    | dacă în fața aplicației stă un load balancer / Cloudflare: `*` sau o listă
    | de IP-uri/CIDR separate prin virgulă. `*` este acceptabil doar dacă
    | aplicația nu este accesibilă direct, ocolind proxy-ul.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    | Token pentru GET /health (Authorization: Bearer ...). Gol = endpoint
    | dezactivat (404). Nu folosi aceeași valoare ca alte secrete.
    */
    'health_token' => env('HEALTH_TOKEN'),

    /*
    | Cereri pe minut, per IP. Valorile sunt generoase (utilizatori reali în
    | spatele aceluiași NAT) și opresc doar abuzul evident.
    */
    'rate_limits' => [
        'cart' => (int) env('RATE_LIMIT_CART', 120),
        'checkout' => (int) env('RATE_LIMIT_CHECKOUT', 20),
    ],

];
