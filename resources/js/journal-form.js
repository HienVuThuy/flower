/* Biểu mẫu tạo/sửa sổ nhật ký — đổi phần bên dưới theo kiểu sổ đang chọn. */
export function initJournalForm() {
    const form = document.querySelector('[data-journal-form]');

    if (!form) {
        return;
    }

    const radios = form.querySelectorAll('[data-kind-radio]');
    const blocks = form.querySelectorAll('[data-for-kinds]');

    if (!radios.length || !blocks.length) {
        return;
    }

    const apply = () => {
        const chon = form.querySelector('[data-kind-radio]:checked');

        if (!chon) {
            return;
        }

        blocks.forEach((block) => {
            const hop = (block.dataset.forKinds || '')
                .split(/\s+/)
                .filter(Boolean)
                .includes(chon.value);

            block.hidden = !hop;
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', apply));

    apply();
}
