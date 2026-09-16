/* Gợi ý sản phẩm hiện ngay khi gõ vào ô tìm kiếm trên thanh đầu trang. */

const DEBOUNCE_MS = 220;
const MIN_LENGTH = 2;

function setup(root) {
    const input = root.querySelector('[data-search-input]');
    const results = root.querySelector('[data-search-results]');

    if (!input || !results) {
        return;
    }

    let timer = null;
    let controller = null;
    let items = [];
    let activeIndex = -1;

    function close() {
        results.hidden = true;
        results.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        items = [];
        activeIndex = -1;
    }

    function highlight(index) {
        items.forEach((el, i) => {
            const on = i === index;
            el.classList.toggle('is-active', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        activeIndex = index;

        if (index >= 0 && items[index]) {
            input.setAttribute('aria-activedescendant', items[index].id);

            items[index].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function render(data) {
        results.replaceChildren();
        items = [];
        activeIndex = -1;

        if (data.corrected) {
            const note = document.createElement('p');
            note.className = 'header-search__note';
            note.textContent = `Đang hiển thị kết quả cho «${data.corrected}»`;
            results.append(note);
        }

        if (!data.items.length) {
            const empty = document.createElement('p');
            empty.className = 'header-search__empty';
            empty.textContent = data.alternative
                ? `Không có kết quả. Thử «${data.alternative}»?`
                : 'Không tìm thấy sản phẩm nào.';
            results.append(empty);
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');

            return;
        }

        data.items.forEach((item, index) => {
            const link = document.createElement('a');
            link.className = 'header-search__item';
            link.href = item.url;
            link.id = `header-search-item-${index}`;
            link.setAttribute('role', 'option');
            link.setAttribute('aria-selected', 'false');

            const media = document.createElement('span');
            media.className = 'header-search__thumb';

            if (item.image) {
                const img = document.createElement('img');
                img.src = item.image;
                img.alt = '';
                img.loading = 'lazy';
                media.append(img);
            }

            const body = document.createElement('span');
            body.className = 'header-search__body';

            const name = document.createElement('span');
            name.className = 'header-search__name';
            name.textContent = item.name;
            body.append(name);

            const meta = document.createElement('span');
            meta.className = 'header-search__meta';

            meta.textContent = [item.category, item.price ?? 'Liên hệ']
                .filter(Boolean)
                .join(' · ');
            body.append(meta);

            link.append(media, body);

            if (!item.inStock) {
                const tag = document.createElement('span');
                tag.className = 'header-search__tag';
                tag.textContent = 'Hết hàng';
                link.append(tag);
            }

            link.addEventListener('mousemove', () => highlight(index));

            results.append(link);
            items.push(link);
        });

        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    async function fetchSuggestions(query) {
        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(
                `/api/goi-y-tim-kiem?q=${encodeURIComponent(query)}`,
                {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                }
            );

            if (!response.ok) {
                close();

                return;
            }

            render(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') {
                close();
            }
        }
    }

    input.addEventListener('input', () => {
        const query = input.value.trim();

        clearTimeout(timer);

        if (query.length < MIN_LENGTH) {
            controller?.abort();
            close();

            return;
        }

        timer = setTimeout(() => fetchSuggestions(query), DEBOUNCE_MS);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (items.length) {
                event.stopPropagation();
                close();
            }

            return;
        }

        if (!items.length) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            const step = event.key === 'ArrowDown' ? 1 : -1;
            const next = (activeIndex + step + items.length) % items.length;

            highlight(activeIndex === -1 && step === -1 ? items.length - 1 : next);

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            items[activeIndex].click();
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            close();
        }
    });

}

export function initSearchSuggest() {
    document.querySelectorAll('[data-search]').forEach(setup);
}
