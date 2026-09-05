/*
 * Đổi khối "thông tin chăm sóc" theo hình thức bán đang chọn.
 * ============================================================
 * Guide mục 4.4: cây chậu và bó hoa không dùng chung bộ thuộc tính
 * chăm sóc. Form in sẵn cả ba hồ sơ, đoạn này chỉ lo ẩn/hiện.
 *
 * ĐÂY CHỈ LÀ TIỆN LỢI, KHÔNG PHẢI HÀNG RÀO.
 * Server vẫn lọc lại care_info theo hình thức ở prepareForValidation()
 * của StoreProductRequest/UpdateProductRequest — tắt JavaScript rồi gửi
 * tay các ô của cây chậu cho một bó hoa thì vẫn bị loại.
 *
 * Bản đồ hình thức -> hồ sơ đặt ở data-attribute do server in ra, để
 * JavaScript không phải chép lại logic của SellingForm::careProfile().
 */

function applyProfile(profileByForm, formSelect) {
    const profile = profileByForm[formSelect.value] ?? null;

    document.querySelectorAll('[data-care-panel]').forEach((panel) => {
        const match = panel.dataset.carePanel === profile;

        panel.hidden = ! match;

        /*
         * Ô bị ẩn phải kèm disabled: trình duyệt vẫn gửi giá trị của
         * input nằm trong phần tử [hidden]. Không disable thì đổi cây
         * chậu sang bó hoa vẫn gửi kèm ánh sáng/đất/phân bón, server
         * phải lọc thêm một lần vô ích.
         */
        panel.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = ! match;
        });
    });
}

export function initCareProfile() {
    const formSelect = document.querySelector('[data-selling-form]');

    if (! formSelect) {
        return;
    }

    let profileByForm;

    try {
        profileByForm = JSON.parse(formSelect.dataset.careProfiles || '{}');
    } catch {
        return;
    }

    applyProfile(profileByForm, formSelect);
    formSelect.addEventListener('change', () => applyProfile(profileByForm, formSelect));
}
