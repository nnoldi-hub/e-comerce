/*
 * Îmbunătățiri progresive pentru filtrele din catalog. Fără JavaScript, formularul
 * funcționează la fel: câmpurile numerice de preț și butonul „Aplică” rămân.
 *
 * - glisorul de preț cu două capete, sincronizat cu câmpurile min/max;
 * - sortarea se aplică imediat, fără buton.
 */

function initPriceRange(root) {
    const min = Number(root.dataset.min);
    const max = Number(root.dataset.max);
    const ui = root.querySelector('[data-range-ui]');
    const low = root.querySelector('[data-range-low]');
    const high = root.querySelector('[data-range-high]');
    const inputMin = root.querySelector('[data-num-min]');
    const inputMax = root.querySelector('[data-num-max]');
    const label = root.querySelector('[data-range-label]');

    if (!ui || !low || !high || !inputMin || !inputMax || !(max > min)) {
        return;
    }

    const clamp = (value) => Math.min(max, Math.max(min, value));
    const percent = (value) => ((value - min) / (max - min)) * 100;

    const paint = () => {
        const a = Number(low.value);
        const b = Number(high.value);

        ui.style.setProperty('--lo', `${percent(a)}%`);
        ui.style.setProperty('--hi', `${percent(b)}%`);

        if (label) {
            label.textContent = `${a} – ${b} lei`;
        }
    };

    // Câmpurile numerice sunt sursa de adevăr; goale = „fără limită”.
    const fromInputs = () => {
        const a = inputMin.value === '' ? min : clamp(Number(inputMin.value));
        const b = inputMax.value === '' ? max : clamp(Number(inputMax.value));

        low.value = String(Math.min(a, b));
        high.value = String(Math.max(a, b));
        paint();
    };

    const fromRanges = (changed) => {
        let a = Number(low.value);
        let b = Number(high.value);

        if (a > b) {
            if (changed === low) {
                a = b;
                low.value = String(a);
            } else {
                b = a;
                high.value = String(b);
            }
        }

        // La capete nu trimitem nimic, ca filtrul să nu apară ca activ degeaba.
        inputMin.value = a <= min ? '' : String(a);
        inputMax.value = b >= max ? '' : String(b);
        paint();
    };

    low.addEventListener('input', () => fromRanges(low));
    high.addEventListener('input', () => fromRanges(high));
    inputMin.addEventListener('input', fromInputs);
    inputMax.addEventListener('input', fromInputs);

    ui.classList.remove('hidden');
    fromInputs();
}

function initAutoSubmit(select) {
    select.addEventListener('change', () => {
        if (select.form) {
            select.form.requestSubmit();
        }
    });

    // Butonul de rezervă pentru situația fără JavaScript nu mai are rost.
    select.form?.querySelectorAll('[data-autosubmit-fallback]').forEach((el) => el.classList.add('hidden'));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-price-range]').forEach(initPriceRange);
    document.querySelectorAll('select[data-autosubmit]').forEach(initAutoSubmit);
});
