# ADR 0018 — SEO și Content-Security-Policy pentru vitrină

Stare: acceptat (Etapa 8F)

## Context
Vitrina (Blade) trebuie să fie indexabilă corect și să reducă suprafața XSS, fără să strice panoul de administrare (Filament/Livewire), care rulează Alpine cu expresii evaluate.

## Decizii
1. **CSP doar pentru vitrină**, în `SecurityHeaders`: nu se aplică pe `/admin`, `/livewire*`, `/up`, `/health`, `/webhooks/*` și nici pe răspunsuri non-HTML. Mod din `security.csp.mode` (`CSP_MODE`): `report-only` (implicit), `enforce`, `off`.
2. **Politica**: `default-src 'self'`; scripturi doar de pe același domeniu (vitrina nu are scripturi inline; JSON-LD este bloc de date, nu script executabil); `style-src 'unsafe-inline'` din cauza `<style>`-ului generat de `@fonts`; `form-action` include Stripe; `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'self'`.
3. **Raportare** la `POST /webhooks/csp-report`: public, deci defensiv (corp ≤ 8 KB, doar câmpuri scalare scurte din lista permisă intră în log, mereu 204, 30/min).
4. **Trecerea la `enforce`**: după câteva zile de trafic real cu zero rapoarte legitime în loguri (căutare `Raport CSP`). Dacă se adaugă scripturi terțe (analytics, chat), domeniile se adaugă explicit în politică.
5. **robots.txt și sitemap.xml dinamice**: robots blochează `/admin`, `/cont`, `/cart`, `/checkout`, `/webhooks`, `/health`; în afara producției interzice tot (staging nu se indexează). Sitemap-ul listează doar pagini publice (produse active cu variante, categorii active), cu cache de o oră și plafon de 50.000 de URL-uri. `public/robots.txt` static se șterge (ar avea prioritate).
6. **Pagini de eroare**: 404/403/419/429 în layout-ul site-ului; 500/503 autonome (fără layout, sesiune sau bază de date, care pot fi chiar cauza erorii).
7. **Date structurate**: `WebSite` + `SearchAction` pe home, `Product` + `Offer` pe pagina de produs.

## Consecințe
- Un script inline nou în vitrină va apărea în loguri ca încălcare, nu va strica nimic cât timp suntem în `report-only`.
- Paginile filtrate/căutate rămân `noindex,follow` (8B); canonical exclude query-ul.
