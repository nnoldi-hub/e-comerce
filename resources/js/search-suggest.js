/*
 * Sugestii pentru căutarea din header (combobox ARIA). Fără JavaScript, formularul
 * funcționează ca înainte: Enter deschide pagina de rezultate.
 *
 * Tastatură: ↓/↑ aleg o sugestie, Enter o deschide, Esc închide lista.
 * Conținutul se construiește cu textContent (nu innerHTML): numele produselor
 * vin din baza de date și nu trebuie interpretate ca HTML.
 */

const MIN_LENGTH = 2;
const DELAY_MS = 200;

function el(tag, className, text) {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
}

function initSearch(form) {
    const input = form.querySelector('input[name="q"]');
    const endpoint = form.dataset.suggestUrl;

    if (!input || !endpoint || !input.parentElement) {
        return;
    }

    const listId = `${input.id || 'q'}-suggestions`;
    const list = el(
        'div',
        'absolute left-0 right-0 top-full z-50 mt-2 hidden overflow-hidden rounded-xl border border-zinc-200 bg-white text-left shadow-xl',
    );
    list.id = listId;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Sugestii de căutare');
    input.parentElement.appendChild(list);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-expanded', 'false');

    const cache = new Map();
    let options = [];
    let active = -1;
    let timer = null;
    let controller = null;

    const close = () => {
        list.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const setActive = (index) => {
        options.forEach((option, i) => {
            const on = i === index;

            option.classList.toggle('bg-brand-50', on);
            option.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        active = index;

        if (index >= 0) {
            input.setAttribute('aria-activedescendant', options[index].id);
            options[index].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };

    const option = (href, id) => {
        const node = el('a', 'flex items-center gap-3 px-3 py-2 text-sm text-zinc-900 hover:bg-brand-50');
        node.href = href;
        node.id = id;
        node.setAttribute('role', 'option');
        node.setAttribute('aria-selected', 'false');
        node.tabIndex = -1;
        options.push(node);

        return node;
    };

    const group = (label) => {
        const wrapper = el('div', 'py-1');
        wrapper.setAttribute('role', 'group');
        wrapper.setAttribute('aria-label', label);
        wrapper.appendChild(el('p', 'px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wider text-zinc-400', label)).setAttribute('aria-hidden', 'true');

        return wrapper;
    };

    const render = (term, data) => {
        list.replaceChildren();
        options = [];

        const products = data.products || [];
        const categories = data.categories || [];

        if (products.length === 0 && categories.length === 0) {
            close();

            return;
        }

        if (categories.length > 0) {
            const wrapper = group('Categorii');

            categories.forEach((category, i) => {
                const node = option(category.url, `${listId}-c${i}`);
                node.appendChild(el('span', 'truncate', category.name));
                wrapper.appendChild(node);
            });

            list.appendChild(wrapper);
        }

        if (products.length > 0) {
            const wrapper = group('Produse');

            products.forEach((product, i) => {
                const node = option(product.url, `${listId}-p${i}`);

                if (product.image) {
                    const img = el('img', 'size-10 shrink-0 rounded-md border border-zinc-200 bg-white object-contain');
                    img.src = product.image;
                    img.alt = '';
                    img.loading = 'lazy';
                    node.appendChild(img);
                } else {
                    node.appendChild(el('span', 'size-10 shrink-0 rounded-md bg-zinc-100'));
                }

                node.appendChild(el('span', 'min-w-0 flex-1 truncate', product.name));

                if (product.price) {
                    node.appendChild(el('span', 'shrink-0 whitespace-nowrap text-sm font-semibold', product.price));
                }

                wrapper.appendChild(node);
            });

            list.appendChild(wrapper);
        }

        const all = option(`${form.action}?q=${encodeURIComponent(term)}`, `${listId}-all`);
        all.className = 'flex items-center justify-between gap-3 border-t border-zinc-100 bg-zinc-50 px-3 py-2.5 text-sm font-medium text-brand-700 hover:bg-brand-50';
        all.appendChild(el('span', 'truncate', `Vezi toate rezultatele pentru „${term}”`));
        list.appendChild(all);

        list.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
        active = -1;
    };

    const search = async (term) => {
        if (cache.has(term)) {
            render(term, cache.get(term));

            return;
        }

        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(`${endpoint}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok) {
                close();

                return;
            }

            const data = await response.json();
            cache.set(term, data);

            // Un răspuns întârziat nu trebuie să înlocuiască ce a tastat clientul între timp.
            if (input.value.trim() === term) {
                render(term, data);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                close();
            }
        }
    };

    input.addEventListener('input', () => {
        const term = input.value.trim();

        window.clearTimeout(timer);

        if (term.length < MIN_LENGTH) {
            controller?.abort();
            close();

            return;
        }

        timer = window.setTimeout(() => search(term), DELAY_MS);
    });

    input.addEventListener('keydown', (event) => {
        const open = !list.classList.contains('hidden');

        if (event.key === 'Escape' && open) {
            close();
            event.preventDefault();

            return;
        }

        if (!open || options.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(active + 1 >= options.length ? 0 : active + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(active - 1 < 0 ? options.length - 1 : active - 1);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            window.location.href = options[active].href;
        }
    });

    input.addEventListener('focus', () => {
        const term = input.value.trim();

        if (term.length >= MIN_LENGTH && cache.has(term)) {
            render(term, cache.get(term));
        }
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) {
            close();
        }
    });

    form.addEventListener('submit', close);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-search-suggest]').forEach(initSearch);
});
