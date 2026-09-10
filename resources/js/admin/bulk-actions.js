/*
 * THAO TÁC HÀNG LOẠT Ở KHU QUẢN TRỊ
 * ============================================================
 * TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN. Không có tệp này thì trang vẫn
 * dùng được: tích tay từng ô, chọn việc, bấm Thực hiện. Ở đây chỉ thêm
 * ba tiện ích:
 *
 *   1. Ô "chọn tất cả" ở đầu bảng.
 *   2. Thanh công cụ chỉ hiện khi thật sự có dòng được chọn.
 *   3. Hỏi lại trước những việc không lùi được.
 *
 * NGHE TRÊN `document`, KHÔNG NGHE TRÊN `form`.
 *
 * Ô tích thuộc về biểu mẫu qua thuộc tính form="..." nên chúng KHÔNG
 * phải con cháu của thẻ <form> trong cây DOM. Sự kiện `change` lan theo
 * cây DOM, nên listener gắn trên <form> không bao giờ chạy — thanh công
 * cụ đứng im và bộ đếm mãi bằng 0.
 *
 * Đây là cái giá của việc dùng thuộc tính form="..." (bắt buộc, vì mỗi
 * dòng đã có một <form> riêng cho nút Xoá và HTML cấm form lồng form).
 * Nghe trên document rồi tự lọc theo id biểu mẫu là cách trả giá đó.
 */

export function initBulkActions() {
    document.querySelectorAll('[data-bulk-form]').forEach((form) => {
        /*
         * GẮN ĐÚNG MỘT LẦN CHO MỖI BIỂU MẪU.
         *
         * Điều hướng quản trị thay ruột trang rồi gọi lại hàm này (xem
         * admin/nav.js). Không có dấu này thì một biểu mẫu còn nằm lại
         * sau lần thay sẽ bị gắn sự kiện lần thứ hai — và một cú bấm
         * "Xoá đã chọn" chạy hai lượt.
         */
        if (form.dataset.bulkReady) return;

        form.dataset.bulkReady = '1';

        const bar = form.querySelector('[data-bulk-bar]');

        if (!bar) return;

        const counter = form.querySelector('[data-bulk-count]');
        const nutBoChon = form.querySelector('[data-bulk-clear]');
        const chonViec = form.querySelector('select[name="viec"]');

        /*
         * Ô "chọn tất cả" nằm ở ĐẦU BẢNG, ngoài thẻ <form> — nên
         * form.querySelector không thấy nó, đúng như với các ô tích.
         *
         * Bỏ sót chỗ này thì "chọn tất cả" vẫn tích được cả bảng (việc
         * đó đi qua listener trên document), nhưng CHÍNH NÓ không bao
         * giờ được cập nhật lại: bỏ chọn hết mà ô vẫn hiện dấu tích, và
         * người dùng đọc màn hình thấy ngược hẳn với thực tế.
         */
        const chonTatCa = Array.from(form.elements)
            .find((el) => el.matches?.('[data-bulk-all]'));

        /*
         * form.elements CHỨ KHÔNG PHẢI form.querySelectorAll.
         *
         * Các ô tích nằm trong bảng, NGOÀI thẻ <form>, và thuộc về form
         * qua thuộc tính form="...". querySelectorAll chỉ tìm trong cây
         * con của form nên sẽ không thấy ô nào — bộ đếm đứng im ở 0 và
         * thanh công cụ không bao giờ hiện.
         *
         * form.elements gom đúng những gì trình duyệt sẽ gửi đi, nên
         * cũng đúng với cả hai cách đặt.
         */
        const oTich = () => Array.from(form.elements)
            .filter((el) => el.matches?.('[data-bulk-item]'));
        const dangChon = () => oTich().filter((o) => o.checked);

        function capNhat() {
            const so = dangChon().length;

            /*
             * Đổi CÂU CHỮ, không ẩn/hiện cả thanh.
             *
             * Chưa chọn gì thì câu hướng dẫn có ích hơn con số 0: nó nói
             * phải làm gì tiếp. Chọn rồi thì con số mới là thứ cần —
             * người dùng vừa tích mười lăm ô rải rác qua hai lần cuộn
             * trang, và đây là thứ duy nhất xác nhận họ tích đúng bằng ấy.
             */
            if (counter) {
                counter.textContent = so === 0
                    ? 'Tích chọn các dòng rồi chọn thao tác'
                    : `Đã chọn ${so} dòng`;
            }

            /*
             * Ô "chọn tất cả" ở trạng thái NỬA VỜI khi mới chọn một
             * phần. Để nó bỏ tích thì người dùng tưởng chưa chọn gì; để
             * nó tích đầy thì tưởng đã chọn hết. `indeterminate` nói
             * đúng sự thật, và chỉ đặt được bằng JavaScript — HTML không
             * có thuộc tính tương ứng.
             */
            if (chonTatCa) {
                chonTatCa.checked = so > 0 && so === oTich().length;
                chonTatCa.indeterminate = so > 0 && so < oTich().length;
            }
        }

        document.addEventListener('change', (e) => {
            const o = e.target;

            // Chỉ nhận ô tích của ĐÚNG biểu mẫu này: một trang về sau có
            // thể có hai bảng cùng dùng thanh hàng loạt.
            if (!o.matches?.('[data-bulk-item], [data-bulk-all]')) return;
            if (o.form !== form) return;

            if (o.matches('[data-bulk-all]')) {
                oTich().forEach((x) => { x.checked = o.checked; });
            }

            capNhat();
        });

        if (nutBoChon) {
            nutBoChon.addEventListener('click', () => {
                oTich().forEach((o) => { o.checked = false; });
                capNhat();
            });
        }

        form.addEventListener('submit', (e) => {
            if (dangChon().length === 0) {
                e.preventDefault();

                return;
            }

            /*
             * HỎI LẠI TRƯỚC VIỆC KHÔNG LÙI ĐƯỢC.
             *
             * Chỉ hỏi khi việc được chọn có gắn cảnh báo — hỏi ở mọi
             * thao tác thì người dùng bấm OK theo phản xạ, và lời hỏi
             * mất tác dụng đúng vào lúc cần nó nhất.
             *
             * confirm() là chốt chặn phía trình duyệt, KHÔNG phải phép
             * kiểm bảo mật; máy chủ vẫn kiểm lại đầy đủ.
             */
            const canhBao = chonViec?.selectedOptions[0]?.dataset.canhBao;

            if (canhBao && !window.confirm(
                canhBao.replace('{so}', String(dangChon().length))
            )) {
                e.preventDefault();
            }
        });

        capNhat();
    });
}
