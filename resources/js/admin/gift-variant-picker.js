/** DANH SÁCH TÍCH CHỌN NHIỀU — trang "Quà kèm sản phẩm": lọc theo tên, đếm số đã chọn, mở ô quy cách khi tích. */
export function initGiftVariantPicker() {
    document.querySelectorAll('[data-chon-nhieu]:not([data-qua-bound])').forEach((khoi) => {
        khoi.dataset.quaBound = '1';

        const loc = khoi.querySelector('[data-chon-nhieu-loc]');
        const dem = khoi.querySelector('[data-chon-nhieu-dem]');
        const cacMuc = [...khoi.querySelectorAll('[data-chon-nhieu-muc]')];

        const capNhat = () => {
            let soChon = 0;

            cacMuc.forEach((muc) => {
                const o = muc.querySelector('input[type="checkbox"]');
                const quyCach = muc.querySelector('[data-qua-chon-quy-cach]');

                if (o.checked) soChon++;
                muc.classList.toggle('is-chon', o.checked);

                if (quyCach) {
                    quyCach.disabled = !o.checked;
                    if (!o.checked) quyCach.value = '';
                }
            });

            if (dem) dem.textContent = String(soChon);
        };

        khoi.addEventListener('change', (e) => {
            if (!e.target.matches('input[type="checkbox"]')) return;

            capNhat();

            /* Tích một món trong danh sách nghĩa là đang chọn nguồn quà đó. */
            const nguon = khoi.dataset.nguon && khoi.closest('form')?.querySelector(`input[name="nguon"][value="${khoi.dataset.nguon}"]`);
            if (nguon && e.target.checked) nguon.checked = true;
        });

        loc?.addEventListener('input', () => {
            const tu = loc.value.trim().toLowerCase();

            cacMuc.forEach((muc) => {
                muc.hidden = tu !== '' && !muc.dataset.ten.includes(tu) && !muc.classList.contains('is-chon');
            });
        });

        loc?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });

        capNhat();
    });
}
