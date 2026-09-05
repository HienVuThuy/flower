<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gỡ hình thức "Chuyển khoản ngân hàng" khỏi hệ thống.
 * ============================================================
 * VÌ SAO GỠ: xác nhận một đơn chuyển khoản đòi hỏi admin mở app ngân
 * hàng, nhìn xem tiền đã về chưa, rồi mới bấm "Đã thanh toán". Đó là một
 * bước THỦ CÔNG do người làm, không phải một luồng tự động — và yêu cầu
 * của môn học là phần thanh toán phải tự động.
 *
 * Không có cách nào tự động hoá nó mà không tích hợp API đối soát của
 * ngân hàng, thứ nằm ngoài phạm vi đồ án. Nên gỡ hẳn thay vì để lại một
 * lựa chọn mà quy trình phía sau không đạt yêu cầu.
 *
 * ============================================================
 * MIGRATION NÀY CHẠY TRƯỚC KHI XOÁ MÃ NGUỒN, và thứ tự đó bắt buộc.
 *
 * `orders.payment_method` được ép kiểu sang enum PaymentMethod trong
 * model. Xoá case `BankTransfer` khỏi enum trong khi trong bảng còn dòng
 * mang giá trị 'bank_transfer' thì Eloquent ném ValueError ngay lúc nạp
 * — trang chi tiết đơn ở khu quản trị sẽ lỗi 500, và lỗi đó chỉ hiện ra
 * khi có người mở đúng đơn cũ đó.
 *
 * ============================================================
 * KHÔNG XOÁ ĐƠN, CHỈ ĐỔI HÌNH THỨC.
 *
 * Đơn hàng là bản ghi lịch sử. Xoá một đơn để cho gọn cơ sở dữ liệu là
 * làm mất doanh thu, mất thống kê, mất cả lịch sử mua của khách.
 *
 * Hình thức mới là COD, và LÝ DO ĐƯỢC GHI VÀO `admin_note` chứ không
 * đổi ngầm: nhìn vào đơn đó sau này vẫn biết nó vốn là đơn chuyển khoản.
 * Đây cũng là thứ duy nhất cho phép lần ngược lại nếu cần.
 */
return new class extends Migration
{
    private const GHI_CHU = '[Hệ thống] Đơn này vốn đặt theo hình thức "Chuyển khoản ngân hàng". '
        . 'Hình thức đó đã được gỡ khỏi hệ thống, đơn được chuyển sang COD.';

    public function up(): void
    {
        $this->doiDonSangCod();
        $this->goKhoiDieuKienCuaMaGiamGia();
        $this->xoaThongTinTaiKhoanNganHang();
    }

    private function doiDonSangCod(): void
    {
        $donCu = DB::table('orders')
            ->where('payment_method', 'bank_transfer')
            ->get(['id', 'admin_note']);

        foreach ($donCu as $don) {
            DB::table('orders')
                ->where('id', $don->id)
                ->update([
                    'payment_method' => 'cod',

                    // Nối vào ghi chú sẵn có chứ không ghi đè: admin có
                    // thể đã viết gì đó trong đó, và mất nó là mất thông
                    // tin do người thật nhập vào.
                    'admin_note' => trim(($don->admin_note ? $don->admin_note . "\n\n" : '') . self::GHI_CHU),
                ]);
        }
    }

    /**
     * Mã giảm giá có thể giới hạn theo hình thức thanh toán.
     *
     * Một mã chỉ cho phép 'bank_transfer' mà hình thức đó không còn tồn
     * tại thì nó thành mã KHÔNG BAO GIỜ dùng được — hỏng âm thầm, và
     * người tạo mã sẽ không hiểu vì sao khách báo lỗi.
     *
     * Bỏ 'bank_transfer' khỏi danh sách. Nếu sau khi bỏ mà danh sách
     * rỗng thì đặt NULL, tức là "không giới hạn hình thức nào" — đúng
     * hơn là một danh sách rỗng, thứ mà Coupon::acceptsPayment() hiểu
     * theo cùng nghĩa nhưng đọc thì mơ hồ.
     */
    private function goKhoiDieuKienCuaMaGiamGia(): void
    {
        $ma = DB::table('coupons')
            ->whereNotNull('payment_methods')
            ->get(['id', 'payment_methods']);

        foreach ($ma as $m) {
            $danhSach = json_decode((string) $m->payment_methods, true);

            if (! is_array($danhSach) || ! in_array('bank_transfer', $danhSach, true)) {
                continue;
            }

            $conLai = array_values(array_filter($danhSach, fn ($v) => $v !== 'bank_transfer'));

            DB::table('coupons')
                ->where('id', $m->id)
                ->update(['payment_methods' => $conLai === [] ? null : json_encode($conLai)]);
        }
    }

    /**
     * Số tài khoản ngân hàng không còn ai đọc tới.
     *
     * Để lại là để lại dữ liệu tài chính thật của chủ cửa hàng trong một
     * bảng không còn mã nguồn nào dùng đến — không ai nhớ nó ở đó, và
     * không ai nhớ để xoá.
     */
    private function xoaThongTinTaiKhoanNganHang(): void
    {
        DB::table('settings')
            ->whereIn('key', ['bank_name', 'bank_account_number', 'bank_account_name'])
            ->delete();
    }

    /**
     * KHÔNG KHÔI PHỤC ĐƯỢC, và nói thẳng ra ở đây.
     *
     * Muốn quay lại thì phải khôi phục cả mã nguồn đã xoá (enum, service
     * sinh mã QR, giao diện), nên một hàm down() đổi ngược dữ liệu chỉ
     * tạo cảm giác an toàn giả: chạy nó xong thì cơ sở dữ liệu có giá
     * trị 'bank_transfer' mà enum không có case đó — đúng cái lỗi 500 mà
     * up() sinh ra để tránh.
     *
     * Ghi chú trong `admin_note` của từng đơn là đường lần ngược thật
     * sự, và nó không phụ thuộc vào migration này.
     */
    public function down(): void
    {
        // Cố ý để trống. Xem chú thích ở trên.
    }
};
