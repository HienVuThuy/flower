/*
 * Gợi ý sản phẩm hiện ngay khi gõ vào ô tìm kiếm trên thanh đầu trang.
 * ============================================================
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Bản thân ô nhập nằm trong một
 * <form method="GET"> đầy đủ: tắt JavaScript thì gõ và bấm Enter vẫn ra
 * đúng trang kết quả, chỉ mất danh sách gợi ý. Vì thế tệp này không bao
 * giờ được chặn sự kiện submit của form.
 *
 * BỐN VIỆC PHẢI LÀM ĐÚNG, đều là chỗ mà bản viết vội hay sai:
 *
 *   1. GỘP PHÍM (debounce). Gõ "hoa hồng" là 8 lần nhấn phím; gọi máy chủ
 *      cả 8 lần thì 7 lần đầu là rác. Chờ 220ms sau phím cuối mới gọi.
 *   2. HUỶ YÊU CẦU CŨ. Mạng không đảm bảo thứ tự trả về — yêu cầu cho
 *      "ho" có thể về SAU yêu cầu cho "hoa" và ghi đè lên kết quả đúng.
 *      AbortController cắt hẳn cái cũ trước khi gửi cái mới.
 *   3. BÀN PHÍM. Mũi tên lên/xuống, Enter để mở, Esc để đóng. Danh sách
 *      gợi ý mà chỉ bấm chuột được thì người dùng bàn phím bị kẹt: tiêu
 *      điểm vẫn ở ô nhập trong khi trên màn hình có một danh sách họ
 *      không với tới được.
 *   4. CHÈN CHỮ AN TOÀN. Tên sản phẩm do người trong cửa hàng nhập, và
 *      luôn dùng textContent chứ không innerHTML — xem ghi chú ở render().
 */

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

    /* ---------- hiển thị ---------- */

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

            // Danh sách có thể dài hơn khung nhìn của nó; không cuộn theo
            // thì mục đang chọn nằm ngoài màn hình mà người dùng không
            // biết mình đang đứng ở đâu.
            items[index].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function render(data) {
        results.replaceChildren();
        items = [];
        activeIndex = -1;

        /*
         * "Đang hiển thị kết quả cho ..." — cùng nguyên tắc với trang
         * danh sách sản phẩm: hệ thống đổi từ khoá của khách thì phải nói
         * ra, không được sửa lặng lẽ.
         */
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
                // alt rỗng có chủ đích: tên sản phẩm đã nằm ngay bên cạnh
                // dưới dạng chữ, đọc lại lần nữa chỉ làm phiền.
                img.alt = '';
                img.loading = 'lazy';
                media.append(img);
            }

            const body = document.createElement('span');
            body.className = 'header-search__body';

            /*
             * textContent, KHÔNG innerHTML.
             *
             * Tên và danh mục là dữ liệu do người dùng nhập ở trang quản
             * trị. Ghép chúng vào innerHTML là mở đúng một lỗ XSS: một
             * tên sản phẩm chứa <script> sẽ chạy trên trình duyệt của mọi
             * khách gõ trúng từ khoá đó. textContent luôn coi chuỗi là
             * chữ, không bao giờ là mã.
             */
            const name = document.createElement('span');
            name.className = 'header-search__name';
            name.textContent = item.name;
            body.append(name);

            const meta = document.createElement('span');
            meta.className = 'header-search__meta';

            // Hàng làm theo yêu cầu không có giá cố định — máy chủ trả về
            // null, và ở đây phải nói "Liên hệ" chứ không được in "null"
            // hay bịa ra số 0.
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

            // Rê chuột tới đâu thì đó là mục đang chọn — nếu không, con
            // trỏ chuột và ô sáng do bàn phím sẽ chỉ vào hai chỗ khác nhau.
            link.addEventListener('mousemove', () => highlight(index));

            results.append(link);
            items.push(link);
        });

        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    /* ---------- gọi máy chủ ---------- */

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
                // Gồm cả 429 khi bị chặn vì gọi quá nhanh. Im lặng đóng
                // danh sách là đúng: ô tìm kiếm vẫn gõ và vẫn submit được,
                // báo lỗi đỏ ở đây chỉ làm khách hoảng vì một tính năng
                // phụ.
                close();

                return;
            }

            render(await response.json());
        } catch (error) {
            // AbortError là do CHÍNH mình huỷ ở lần gõ tiếp theo, không
            // phải sự cố — bỏ qua, đừng đóng danh sách đang hiện.
            if (error.name !== 'AbortError') {
                close();
            }
        }
    }

    /* ---------- sự kiện ---------- */

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
                // Lần Esc đầu chỉ đóng danh sách, giữ nguyên chữ đã gõ.
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
            // Cộng thêm items.length trước khi chia dư: trong JavaScript
            // (-1 % 6) ra -1 chứ không ra 5, nên mũi tên lên từ mục đầu sẽ
            // nhảy ra ngoài mảng.
            const next = (activeIndex + step + items.length) % items.length;

            highlight(activeIndex === -1 && step === -1 ? items.length - 1 : next);

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            // Có mục đang chọn thì Enter mở mục đó, không submit form.
            // Chưa chọn gì thì để form chạy như thường -> trang kết quả
            // đầy đủ. Đúng thói quen người dùng đã quen ở mọi ô tìm kiếm.
            event.preventDefault();
            items[activeIndex].click();
        }
    });

    /*
     * Bấm ra ngoài thì đóng danh sách gợi ý.
     *
     * Chỉ đóng DANH SÁCH, không đóng gì khác — ô nhập nay nằm cố định
     * trên thanh header nên không có gì để đóng. Chữ khách đã gõ vẫn còn
     * nguyên, đúng như mọi ô tìm kiếm khác họ từng dùng.
     */
    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            close();
        }
    });

}

export function initSearchSuggest() {
    document.querySelectorAll('[data-search]').forEach(setup);
}
