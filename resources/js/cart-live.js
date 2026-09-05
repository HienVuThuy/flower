import { guiForm, coHoTro, capNhatSoGio } from './ajax';
import { showToast } from './flash';

/*
 * Sửa giỏ hàng mà KHÔNG tải lại trang.
 * ============================================================
 * VÌ SAO CẦN: trang giỏ hàng là nơi khách bấm nhiều nhất trước khi trả
 * tiền — tăng số lượng, bỏ một món, bỏ tích một món để dành lần sau. Mỗi
 * cú bấm trước đây là một lần tải lại cả trang: ảnh nháy trắng, vị trí
 * cuộn nhảy về đầu, và với giỏ sáu bảy món thì phải cuộn lại tìm đúng
 * dòng vừa sửa. Sửa ba dòng là ba lần như vậy.
 *
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Mọi thao tác ở đây đều là <form
 * method="POST"> thật tới đúng route thật. Tắt JavaScript thì trang chạy
 * y như cũ, chỉ là tải lại.
 *
 * BỐN ĐIỀU PHẢI ĐÚNG:
 *
 *   1. TIỀN DO MÁY CHỦ TÍNH. Tệp này KHÔNG cộng trừ một con số nào. Máy
 *      chủ vẽ lại cả khối giỏ (shop/cart/partials/noi-dung.blade.php) và
 *      trả về HTML; ở đây chỉ thay chỗ. Tự tính tạm tính/giảm giá/phí
 *      giao ở trình duyệt là có hai bản luật tính tiền, và bản khách
 *      nhìn thấy sẽ là bản sai.
 *
 *   2. GẮN SỰ KIỆN THEO KIỂU UỶ QUYỀN. Cả khối giỏ bị thay mới sau mỗi
 *      lần bấm, nên listener gắn thẳng vào từng nút sẽ chết ngay sau
 *      thao tác đầu tiên. Nghe ở khung ngoài — thứ không bao giờ bị
 *      thay — thì mọi nút mới sinh ra đều chạy.
 *
 *   3. MỘT LƯỢT MỘT. Bấm xoá hai dòng thật nhanh sẽ có hai câu trả lời
 *      về không theo thứ tự, và câu về sau (vẽ theo trạng thái cũ hơn)
 *      ghi đè câu về trước — dòng vừa xoá hiện lại. Khoá trong lúc chờ.
 *
 *   4. HỎNG THÌ QUAY VỀ CÁCH CŨ. Mất mạng, phiên hết hạn, máy chủ trả
 *      HTML thay vì JSON: gửi biểu mẫu như thường. Khách vẫn sửa được
 *      giỏ, chỉ là trang tải lại.
 */

/**
 * Nút "Cập nhật lựa chọn" chỉ tồn tại cho trường hợp KHÔNG có JavaScript.
 *
 * Có JavaScript thì ô đánh dấu tự gửi ngay khi tích, nên nút không còn
 * việc gì. Để lại một nút bấm vào không thấy gì thay đổi là để lại một
 * thứ khiến người dùng tưởng hệ thống hỏng.
 *
 * Phải gọi lại SAU MỖI LẦN vẽ lại: HTML mới từ máy chủ luôn có nút đó.
 */
function donNutThua(khung) {
    khung.querySelector('[data-cart-select-submit]')?.remove();
}

/**
 * Trả tiêu điểm bàn phím về đúng chỗ vừa thao tác.
 *
 * Thay innerHTML là vứt bỏ phần tử đang được chọn, và tiêu điểm rơi về
 * <body>. Người dùng bàn phím vừa bấm Enter ở ô số lượng dòng thứ tư sẽ
 * phải bấm Tab lại từ đầu trang — đúng thứ tính năng này sinh ra để
 * tránh.
 *
 * Phần tử cũ đã biến mất nên tìm lại theo id, không giữ tham chiếu.
 */
function traTieuDiem(id) {
    if (!id) {
        return;
    }

    const moi = document.getElementById(id);

    // Không còn nữa (vừa xoá đúng dòng đó) thì thôi — ép tiêu điểm sang
    // một dòng khác chỉ làm người dùng lạc chỗ.
    if (moi) {
        moi.focus();
    }
}

export function initCartLive() {
    const khung = document.querySelector('[data-cart-live]');

    // Không phải trang giỏ hàng, hoặc trình duyệt quá cũ: không làm gì,
    // biểu mẫu bên dưới tự chạy như thường.
    if (!khung || !coHoTro()) {
        return;
    }

    donNutThua(khung);

    let dangGui = false;

    /**
     * Gửi một biểu mẫu của giỏ rồi thay lại cả khối.
     *
     * @param {HTMLFormElement} form
     * @param {boolean} imLang  true = không hiện toast (thao tác tự nó
     *                          đã nhìn thấy được, ví dụ tích chọn)
     */
    const gui = async (form, imLang = false) => {
        if (dangGui) {
            return;
        }

        dangGui = true;

        // Làm mờ khối trong lúc chờ: không có dấu hiệu gì thì khách bấm
        // lại lần nữa vì tưởng cú bấm đầu không ăn.
        khung.classList.add('is-sending');

        // Nhớ trước khi khối bị thay — sau đó phần tử này không còn.
        const tieuDiem = document.activeElement?.id;

        try {
            const { ok, data } = await guiForm(form);

            if (!ok) {
                /*
                 * 422 từ máy chủ: số lượng vượt tồn kho, dòng không còn
                 * tồn tại... KHÔNG thay khối — trạng thái trên màn hình
                 * vẫn là trạng thái đúng của máy chủ, chỉ là thao tác
                 * vừa rồi bị từ chối. Nói lý do rồi để nguyên.
                 */
                showToast(data.message ?? 'Không thực hiện được thao tác này.', 'error');

                return;
            }

            khung.innerHTML = data.html;
            donNutThua(khung);
            capNhatSoGio(data.cartCount);
            traTieuDiem(tieuDiem);

            if (!imLang) {
                showToast(data.message ?? 'Đã cập nhật giỏ hàng.');
            }
        } finally {
            dangGui = false;
            khung.classList.remove('is-sending');
        }
    };

    /*
     * MỘT trình lắng nghe cho cả ba biểu mẫu (sửa số lượng, xoá, chọn
     * món). Chúng khác nhau ở route và ở phương thức, nhưng giống nhau ở
     * chỗ quan trọng nhất: gửi xong thì cả khối giỏ phải vẽ lại.
     */
    khung.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-cart-form]');

        if (!form) {
            return;
        }

        event.preventDefault();

        // Biểu mẫu chọn món không cần toast: ô đánh dấu và dòng chữ
        // "Đang chọn n/m món" đã nói đủ, thêm toast là nói hai lần.
        gui(form, form.hasAttribute('data-cart-select')).catch(() => {
            /*
             * form.submit() KHÔNG bắn lại sự kiện submit nên không có
             * vòng lặp. Im lặng nuốt lỗi ở đây mới là hỏng: khách bấm
             * xoá, không có gì xảy ra, và không ai biết vì sao.
             */
            dangGui = false;
            form.submit();
        });
    });

    /*
     * Tích một ô -> gửi luôn, không phải bấm nút.
     * Ô "chọn tất cả" -> bật/tắt mọi ô rồi gửi.
     *
     * Cả hai nghe ở khung ngoài nên vẫn chạy với những ô vừa được máy
     * chủ vẽ lại.
     */
    /*
     * THÊM PHỤ KIỆN TỪ KHỐI "CÓ THỂ BẠN CẦN THÊM" -> VẼ LẠI GIỎ.
     *
     * Khối mua kèm nằm ngay trong trang này và dùng chung biểu mẫu với
     * thẻ sản phẩm ở mọi nơi khác, nên add-to-cart.js xử lý nó. Nó cập
     * nhật huy hiệu trên thanh trên cùng rồi dừng — đúng cho trang chủ,
     * nhưng ở đây thì danh sách hàng và phần tiền ngay bên cạnh vẫn là
     * số cũ: món vừa thêm không hiện ra, tổng tiền không nhúc nhích.
     * Khách bấm lại lần nữa vì tưởng hụt.
     *
     * Nghe sự kiện `cart:added` thay vì sửa add-to-cart.js: tệp kia
     * không cần biết trang giỏ hàng tồn tại.
     */
    document.addEventListener('cart:added', async () => {
        const url = khung.dataset.cartLive;

        if (!url || dangGui) {
            return;
        }

        dangGui = true;

        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();

            khung.innerHTML = data.html;
            donNutThua(khung);
        } catch {
            /*
             * IM LẶNG BỎ QUA — cố ý, và đây là chỗ duy nhất trong tệp
             * này được phép làm vậy. Món hàng ĐÃ vào giỏ và toast của
             * add-to-cart.js đã nói điều đó; việc vẽ lại chỉ là làm cho
             * đẹp. Gửi lại biểu mẫu ở đây là thêm món thứ hai.
             */
        } finally {
            dangGui = false;
        }
    });

    khung.addEventListener('change', (event) => {
        const form = khung.querySelector('[data-cart-select]');

        if (!form) {
            return;
        }

        if (event.target.matches('[data-cart-pick-all]')) {
            khung.querySelectorAll('[data-cart-pick]').forEach((o) => {
                o.checked = event.target.checked;
            });
        } else if (!event.target.matches('[data-cart-pick]')) {
            return;
        }

        gui(form, true).catch(() => {
            dangGui = false;
            form.submit();
        });
    });
}
