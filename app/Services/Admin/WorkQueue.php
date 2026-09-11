<?php

namespace App\Services\Admin;

use App\Enums\InquiryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockReceiptStatus;
use App\Models\BulkOrderInquiry;
use App\Models\CommunityPost;
use App\Models\Order;
use App\Models\Review;
use App\Models\StockReceipt;
use App\Services\Analytics\InventoryReport;

/**
 * HÀNG ĐỢI VIỆC của trang quản trị.
 * ============================================================
 * Đây là thứ đầu tiên admin nhìn thấy khi mở trang, và nó phải trả lời
 * đúng một câu: HÔM NAY PHẢI LÀM GÌ.
 *
 * Bản trước, chỗ này là bốn con số — bao nhiêu danh mục, bao nhiêu sản
 * phẩm, bao nhiêu khách. Không con số nào nói cho ai biết phải làm gì;
 * chúng gần như không đổi từ ngày này sang ngày khác, và người ta học
 * cách lướt qua.
 *
 * ============================================================
 * BA LUẬT CỦA MỘT HÀNG ĐỢI ĐÁNG TIN:
 *
 *   1. MỖI MỤC PHẢI CÓ NGƯỜI ĐỘNG TAY ĐƯỢC. "42 sản phẩm" không phải
 *      việc. "2 đơn chưa có vận đơn" thì có.
 *
 *   2. MỖI MỤC DẪN THẲNG TỚI DANH SÁCH ĐÃ LỌC SẴN. Hiện con số rồi bắt
 *      admin tự đi lọc lại là bỏ dở việc giữa chừng.
 *
 *   3. SỐ 0 THÌ BIẾN MẤT. Một danh sách toàn "0 đơn chờ xác nhận" là
 *      danh sách không ai đọc, và đọc mãi thành quen bỏ qua — kể cả hôm
 *      con số khác 0.
 *
 * ============================================================
 * VÌ SAO LÀ MỘT LỚP RIÊNG chứ không nằm trong controller: cùng một câu
 * hỏi "còn việc gì" sẽ được hỏi ở nhiều chỗ (trang tổng quan, huy hiệu
 * trên thanh điều hướng, sau này là thông báo). Định nghĩa nằm hai nơi
 * là hai nơi đó sẽ lệch nhau, và không ai biết nơi nào đúng.
 */
class WorkQueue
{
    public function __construct(
        private readonly InventoryReport $kho,
    ) {
    }

    /**
     * Mọi việc đang chờ, đã bỏ mục bằng 0, XẾP THEO MỨC GẤP.
     *
     * @return list<array{label: string, count: int, url: string, tone: string, hint: string}>
     */
    public function items(): array
    {
        /*
         * MỘT LẦN ĐỌC KHO CHO CẢ HAI MỤC.
         *
         * `tongQuan()` và `sapHet()` đều gọi `rows()`, mà `rows()` quét
         * toàn bộ sản phẩm cùng quy cách. Gọi hai lần là làm hai lần
         * cùng một việc cho một trang.
         */
        $tongQuanKho = $this->kho->trongVong(30)->tongQuan();

        $viec = [
            /*
             * ĐƠN ĐÃ HUỶ MÀ KHÁCH ĐÃ TRẢ TIỀN = CỬA HÀNG ĐANG NỢ KHÁCH.
             *
             * Đứng đầu vì đây là việc duy nhất trong cả danh sách liên
             * quan tới TIỀN CỦA NGƯỜI KHÁC. Mọi mục còn lại chậm một
             * ngày thì cửa hàng thiệt; mục này chậm một ngày thì khách
             * thiệt.
             */
            [
                'label' => 'đơn đã huỷ cần hoàn tiền cho khách',
                'count' => Order::query()
                    ->where('status', OrderStatus::Cancelled)
                    ->where('payment_status', PaymentStatus::Paid)
                    ->count(),
                'url' => route('admin.orders.index', ['status' => 'cancelled', 'payment' => 'paid']),
                'tone' => 'danger',
                'hint' => 'Khách đã trả tiền cho đơn không còn nữa. Hệ thống không tự chuyển tiền lại.',
            ],

            /*
             * HOÀN QUA MOMO MÀ KHÔNG RÕ KẾT QUẢ — ngang hàng với nợ khách.
             *
             * Tiền có thể đã về ví khách, có thể chưa. Để lâu thì hoặc
             * khách gọi hỏi, hoặc có người thấy "còn hoàn được" và hoàn
             * thêm lần nữa.
             */
            [
                'label' => 'đơn có khoản hoàn tiền MoMo chưa rõ kết quả',
                'count' => Order::refundPending()->count(),
                'url' => route('admin.orders.index', ['hoan_tien' => 'chua-ro']),
                'tone' => 'danger',
                'hint' => 'MoMo không trả lời lúc hoàn. Kiểm trên cổng MoMo rồi xác nhận; đừng hoàn lại lần nữa.',
            ],

            [
                'label' => 'đơn chờ xác nhận',
                'count' => Order::where('status', OrderStatus::Pending)->count(),
                'url' => route('admin.orders.index', ['status' => 'pending']),
                'tone' => 'warning',
                'hint' => 'Khách đã đặt và đang đợi cửa hàng nhận đơn.',
            ],

            /*
             * ĐÃ NHẬN ĐƠN NHƯNG CHƯA CÓ VẬN ĐƠN — đơn đứng im.
             *
             * Đơn trả qua MoMo tự tạo vận đơn ngay sau khi thanh toán;
             * đơn COD thì KHÔNG — phải có người bấm. Trước đây không màn
             * hình nào hiện ra khoảng trống đó: đơn nằm ở "Đã xác nhận",
             * trông như đang chạy, mà thực tế chưa ai gọi shipper.
             */
            [
                'label' => 'đơn đã nhận nhưng chưa có vận đơn',
                'count' => Order::awaitingWaybill()->count(),
                'url' => route('admin.orders.index', ['van_don' => 'cho-tao']),
                'tone' => 'warning',
                'hint' => 'Đơn COD không tự tạo vận đơn. Chưa tạo thì hàng chưa đi.',
            ],

            /*
             * HẾT HÀNG MÀ VẪN BÀY BÁN = ĐANG MẤT ĐƠN NGAY LÚC NÀY.
             *
             * Đếm bằng InventoryReport chứ KHÔNG bằng
             * `products.stock_quantity <= 0`. Hai lý do:
             *
             *   - Sản phẩm có quy cách giữ tồn ở TỪNG QUY CÁCH; cột trên
             *     bảng sản phẩm không phải thứ khách mua. Đếm ở đó là bỏ
             *     sót đúng những món đang hết.
             *   - Hết hàng của một sản phẩm ĐÃ ẨN thì không mất đơn nào.
             *
             * Dùng chung một hàm với trang Tồn kho để hai màn hình không
             * bao giờ nói hai con số khác nhau cho cùng một câu hỏi.
             */
            [
                'label' => 'mặt hàng đã hết nhưng vẫn đang bày bán',
                'count' => $tongQuanKho['out'],
                'url' => route('admin.inventory.index'),
                'tone' => 'warning',
                'hint' => 'Khách vẫn bấm vào được nhưng không mua được.',
            ],

            [
                'label' => 'mặt hàng còn dưới 14 ngày bán',
                'count' => $tongQuanKho['low'],
                'url' => route('admin.inventory.index'),
                'tone' => 'info',
                'hint' => 'Tính theo tốc độ bán 30 ngày qua, không theo số lượng còn lại.',
            ],

            /*
             * PHIẾU NHẬP CÒN NHÁP = HÀNG ĐÃ VỀ NHƯNG KHO CHƯA CỘNG.
             *
             * Lập phiếu KHÔNG cộng vào kho — phải bấm "Ghi sổ". Người
             * lập bị gọi đi giữa chừng là phiếu nằm mãi ở nháp, tồn kho
             * hiển thị thiếu, và trang Tồn kho giục nhập thêm đúng món
             * đang chất trong kho.
             */
            [
                'label' => 'phiếu nhập còn nháp, chưa cộng vào kho',
                'count' => StockReceipt::where('status', StockReceiptStatus::Draft)->count(),
                'url' => route('admin.stock-receipts.index', ['trang-thai' => StockReceiptStatus::Draft->value]),
                'tone' => 'info',
                'hint' => 'Hàng đã nhận nhưng tồn kho chưa được cộng thêm.',
            ],

            /*
             * ĐÁNH GIÁ THẤP CHƯA TRẢ LỜI.
             *
             * Một lời phàn nàn không ai trả lời nằm công khai trên trang
             * sản phẩm. Đánh giá 5 sao không cần trả lời gấp; 1-2 sao thì
             * có, và chúng lẫn giữa hàng chục đánh giá tốt.
             */
            [
                'label' => 'đánh giá 1-2 sao chưa được trả lời',
                'count' => Review::query()
                    ->where('rating', '<=', 2)
                    ->whereNull('admin_reply')
                    ->count(),
                'url' => route('admin.reviews.index', ['sao' => 'thap', 'tra_loi' => 'chua']),
                'tone' => 'info',
                'hint' => 'Lời phàn nàn đang hiển thị công khai mà cửa hàng chưa nói gì.',
            ],

            [
                'label' => 'bài cộng đồng chờ duyệt',
                'count' => CommunityPost::pending()->count(),
                'url' => route('admin.community.index'),
                'tone' => 'info',
                'hint' => 'Khách đã đăng nhưng chưa ai xem qua.',
            ],

            [
                'label' => 'yêu cầu báo giá chưa xử lý',
                'count' => BulkOrderInquiry::where('status', InquiryStatus::New)->count(),
                'url' => route('admin.bulk-inquiries.index', ['status' => InquiryStatus::New->value]),
                'tone' => 'info',
                'hint' => 'Khách hỏi mua số lượng lớn và đang đợi trả lời.',
            ],
        ];

        return array_values(array_filter($viec, fn (array $v) => $v['count'] > 0));
    }
}
