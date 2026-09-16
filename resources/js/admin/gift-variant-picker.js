/** CHỌN QUY CÁCH THEO SẢN PHẨM — trang "Quà kèm sản phẩm" */
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

                if (datLai) chonQuyCach.value = '';

                chonQuyCach.disabled = sanPham === '';
                chonQuyCach.title = sanPham !== '' && !coQuyCach ? 'Sản phẩm này không có quy cách' : '';
            };

            capNhat(false);
            chonSanPham.addEventListener('change', () => capNhat(true));
        });
}
