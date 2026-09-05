/*
 * Gửi biểu mẫu bằng fetch — phần dùng chung.
 * ============================================================
 * VÌ SAO TÁCH RA: ba chỗ trong hệ thống cùng làm một việc (thêm vào giỏ,
 * sửa giỏ, yêu thích): gửi đúng biểu mẫu đang có tới đúng route đang có,
 * chỉ khác là nhận JSON thay vì một trang HTML mới. Chép đoạn fetch này
 * ba lần thì ba lần phải nhớ đủ bốn thứ bên dưới, và lần thứ ba sẽ quên
 * một thứ.
 *
 * ĐÂY LÀ PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Mọi biểu mẫu gọi qua đây đều
 * là <form method="POST"> thật, tới đúng route thật, và vẫn chạy khi
 * JavaScript hỏng. Máy chủ vẫn kiểm tra lại tất cả — đây là chuyện tiện
 * tay cho người dùng, không phải chuyện tin tưởng trình duyệt.
 */

/**
 * Gửi một biểu mẫu và trả về câu trả lời JSON.
 *
 * BỐN THỨ PHẢI ĐÚNG, và đó là lý do hàm này tồn tại:
 *
 *   1. `new FormData(form)` — lấy nguyên si những gì biểu mẫu sẽ gửi,
 *      gồm cả _token của CSRF và _method của PATCH/DELETE. Tự dựng body
 *      bằng tay là tự chép lại danh sách trường, và nó sẽ lệch khỏi
 *      biểu mẫu ngay lần sửa giao diện tiếp theo.
 *
 *   2. `Accept: application/json` — để Laravel trả JSON thay vì chuyển
 *      hướng 302 mà fetch sẽ lặng lẽ đi theo.
 *
 *   3. `X-Requested-With` — có middleware chỉ nhìn header này để quyết
 *      định dạng câu trả lời.
 *
 *   4. `credentials: same-origin` — phiên đăng nhập và giỏ của khách
 *      vãng lai đều nằm trong cookie. Thiếu dòng này thì máy chủ thấy
 *      một người lạ.
 *
 * KHÔNG BẮT LỖI Ở ĐÂY. Mất mạng, phiên hết hạn, máy chủ trả HTML — mỗi
 * nơi gọi có cách lùi khác nhau (thường là gửi biểu mẫu như cũ), nên
 * lỗi phải ném ra cho nơi gọi quyết định.
 *
 * @param  {HTMLFormElement} form
 * @return {Promise<{ok: boolean, data: any}>}
 */
export async function guiForm(form) {
    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    return { ok: response.ok, data: await response.json() };
}

/** Trình duyệt có đủ thứ cần để chạy phần này không. */
export function coHoTro() {
    return Boolean(window.fetch && window.FormData);
}

/**
 * Cập nhật huy hiệu số món trên icon giỏ hàng ở thanh trên cùng.
 *
 * Ở ĐÂY chứ không ở add-to-cart.js: giờ có ba chỗ làm số này đổi (thêm
 * vào giỏ, sửa số lượng, xoá món), và cả ba phải đổi cùng một cách —
 * gồm cả nhãn cho trình đọc màn hình, thứ dễ quên nhất.
 *
 * @param {number|undefined} count
 */
export function capNhatSoGio(count) {
    if (typeof count !== 'number') {
        return;
    }

    const badge = document.querySelector('[data-cart-badge]');
    const link = document.querySelector('[data-cart-link]');

    if (badge) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count < 1;
    }

    // Người dùng trình đọc màn hình không nhìn thấy huy hiệu, họ nghe
    // cái nhãn này — bỏ quên nó là bỏ quên đúng nhóm người cần nó nhất.
    if (link) {
        link.setAttribute(
            'aria-label',
            count > 0 ? `Giỏ hàng (${count} sản phẩm)` : 'Giỏ hàng (trống)',
        );
    }
}
