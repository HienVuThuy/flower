/**
 * CHỌN QUY CÁCH THEO SẢN PHẨM — trang "Quà kèm sản phẩm"
 * ============================================================
 * Bước 1 chọn sản phẩm quà, bước 2 mới mở ô quy cách, và ô đó CHỈ gồm quy
 * cách của đúng sản phẩm vừa chọn. Đổi sản phẩm thì bỏ quy cách cũ.
 *
 * Mục tiêu: không bao giờ "sản phẩm A nhưng quy cách của sản phẩm B".
 *
 * TĂNG CƯỜNG, KHÔNG PHẢI LỚP BẢO VỆ: máy chủ dựng sẵn trạng thái đúng cho lần
 * tải đầu (ô khoá khi chưa chọn, quy cách của sản phẩm khác bị ẩn) và vẫn tự
 * kiểm quy cách thuộc sản phẩm (ProductGiftController::vatPhamTuSanPham).
 */
export function initGiftVariantPicker() {
    document
        .querySelectorAll('[data-qua-chon-san-pham]:not([data-qua-bound])')
        .forEach((chonSanPham) => {
            chonSanPham.dataset.quaBound = '1';

            const form = chonSanPham.closest('form');
            const chonQuyCach = form?.querySelector('[data-qua-chon-quy-cach]');

            if (!chonQuyCach) return;

            const capNhat = (datLai) => {
                const sanPham = chonSanPham.value;
                let coQuyCach = false;

                chonQuyCach.querySelectorAll('option[data-san-pham]').forEach((opt) => {
                    const thuoc = sanPham !== '' && opt.dataset.sanPham === sanPham;

                    opt.hidden = !thuoc;
                    opt.disabled = !thuoc;
                    coQuyCach = coQuyCach || thuoc;
                });

                // Đổi sản phẩm → quy cách cũ không còn nghĩa, về "Không chọn quy cách".
                if (datLai) chonQuyCach.value = '';

                chonQuyCach.disabled = sanPham === '';
                chonQuyCach.title = sanPham !== '' && !coQuyCach ? 'Sản phẩm này không có quy cách' : '';
            };

            capNhat(false);
            chonSanPham.addEventListener('change', () => capNhat(true));
        });
}
