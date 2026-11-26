/**
 * Autocomplete pre vyhľadávacie pole v menu (#navbarSearch).
 *
 * - Návrhy z /hladat/navrhy?q=… (hríbiky + blog), po 200 ms od posledného písmena.
 * - Klávesnica: ↓/↑ výber, Enter otvorí vybraný návrh (bez výberu odošle formulár → /hladat),
 *   Esc zavrie. ARIA vzor „combobox + listbox“.
 * - Bez JavaScriptu funguje formulár normálne (Enter → stránka s výsledkami).
 * - Dáta sa vkladajú cez textContent, nikdy cez innerHTML (ochrana pred XSS).
 */
(function () {
    'use strict';

    const MIN_LENGTH = 2;
    const DEBOUNCE_MS = 200;

    const form = document.getElementById('navbarSearch');
    const input = document.getElementById('navbarSearchInput');
    const menu = document.getElementById('navbarSearchSuggestions');
    if (!form || !input || !menu || !window.fetch) {
        return;
    }

    const labels = {
        mushroom: form.dataset.labelMushrooms,
        post: form.dataset.labelBlog,
        all: form.dataset.labelAll,
        none: form.dataset.labelNone,
    };
    const cache = new Map();
    let debounceTimer = null;
    let controller = null;
    let activeIndex = -1;

    function options() {
        return Array.from(menu.querySelectorAll('[role="option"]'));
    }

    function open() {
        menu.classList.add('show');
        input.setAttribute('aria-expanded', 'true');
    }

    function close() {
        menu.classList.remove('show');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    }

    function setActive(index) {
        const items = options();
        if (items.length === 0) {
            return;
        }
        activeIndex = (index + items.length) % items.length;
        items.forEach(function (item, i) {
            const active = i === activeIndex;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', items[activeIndex].id);
        items[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function createOption(id, href, title, subtitle) {
        const a = document.createElement('a');
        a.className = 'dropdown-item text-wrap';
        a.id = id;
        a.href = href;
        a.setAttribute('role', 'option');
        a.setAttribute('aria-selected', 'false');
        a.tabIndex = -1;

        const titleEl = document.createElement('div');
        titleEl.textContent = title;
        a.appendChild(titleEl);

        if (subtitle) {
            const sub = document.createElement('small');
            sub.className = 'text-body-secondary';
            sub.textContent = subtitle;
            a.appendChild(sub);
        }

        return a;
    }

    function render(data) {
        menu.replaceChildren();
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');

        if (data.items.length === 0) {
            const empty = document.createElement('span');
            empty.className = 'dropdown-item-text text-body-secondary';
            empty.textContent = labels.none;
            menu.appendChild(empty);
            open();
            return;
        }

        let lastType = null;
        data.items.forEach(function (item, i) {
            if (item.type !== lastType) {
                const header = document.createElement('h6');
                header.className = 'dropdown-header';
                header.textContent = labels[item.type] || '';
                menu.appendChild(header);
                lastType = item.type;
            }
            menu.appendChild(createOption('navbarSearchOption' + i, item.url, item.title, item.subtitle));
        });

        const divider = document.createElement('hr');
        divider.className = 'dropdown-divider';
        menu.appendChild(divider);

        const all = createOption('navbarSearchOptionAll', data.searchUrl, labels.all, null);
        all.classList.add('fw-semibold');
        menu.appendChild(all);

        open();
    }

    function load(query) {
        if (cache.has(query)) {
            render(cache.get(query));
            return;
        }

        if (controller) {
            controller.abort();
        }
        controller = new AbortController();

        const url = form.dataset.suggestUrl + '?q=' + encodeURIComponent(query);
        fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                cache.set(query, data);
                // Používateľ medzitým mohol písať ďalej — zobraz len aktuálny dotaz.
                if (input.value.trim() === query) {
                    render(data);
                }
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    close(); // pri chybe proste bez návrhov; Enter stále funguje
                }
            });
    }

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const query = input.value.trim();

        if (query.length < MIN_LENGTH) {
            if (controller) {
                controller.abort();
            }
            close();
            return;
        }

        debounceTimer = setTimeout(function () {
            load(query);
        }, DEBOUNCE_MS);
    });

    input.addEventListener('keydown', function (event) {
        const isOpen = menu.classList.contains('show');

        switch (event.key) {
            case 'ArrowDown':
                if (!isOpen) {
                    const query = input.value.trim();
                    if (query.length >= MIN_LENGTH) {
                        load(query);
                    }
                    return;
                }
                event.preventDefault();
                setActive(activeIndex + 1);
                break;
            case 'ArrowUp':
                if (isOpen) {
                    event.preventDefault();
                    setActive(activeIndex - 1);
                }
                break;
            case 'Enter':
                if (isOpen && activeIndex >= 0) {
                    event.preventDefault();
                    window.location.href = options()[activeIndex].href;
                }
                // bez výberu → formulár sa odošle normálne na /hladat
                break;
            case 'Escape':
                if (isOpen) {
                    event.preventDefault();
                    close();
                }
                break;
            case 'Tab':
                close();
                break;
        }
    });

    input.addEventListener('focus', function () {
        const query = input.value.trim();
        if (query.length >= MIN_LENGTH && cache.has(query)) {
            render(cache.get(query));
        }
    });

    // Klik mimo formulára zavrie návrhy.
    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            close();
        }
    });

    // Návrat tlačidlom „späť“ (bfcache) — nenechávaj otvorené staré návrhy.
    window.addEventListener('pageshow', close);
})();
