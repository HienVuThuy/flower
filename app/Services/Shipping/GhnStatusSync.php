<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Audit\ActivityLogger;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Log;

/**
 * Hỏi GHN xem hàng đang ở đâu, rồi cập nhật đơn theo câu trả lời.
 * ============================================================
 * VÌ SAO PHẢI CÓ: `orders.shipping_status` trước đây chỉ được ghi ĐÚNG
 * HAI LẦN — lúc tạo vận đơn (`ready_to_pick`) và lúc huỷ (`cancel`).
 * Sau đó không bao giờ đổi nữa. Một đơn đã giao xong từ ba hôm trước vẫn
 * hiện "chờ lấy hàng" cho tới khi có người vào bấm tay.
 *
 * Đó không phải thiếu tính năng, đó là một con số SAI hiển thị cho cả
 * khách lẫn admin.
 *
 * ============================================================
 * ĐÂY LÀ CÁCH XÁC NHẬN COD MÀ KHÔNG CẦN AI BẤM TAY.
 *
 * Đơn COD chỉ được trả tiền khi shipper giao tận nơi và thu tiền mặt.
 * Phần mềm không nhìn thấy việc đó — nên trước đây admin phải tự hỏi rồi
 * tự bấm "Đã thanh toán". Đúng cái kiểu thủ công mà môn học không chấp
 * nhận.
 *
 * Nhưng GHN CHÍNH LÀ shipper, và GHN có API nói rõ vận đơn đã ở trạng
 * thái `delivered` hay chưa. Trạng thái đó nghĩa là hàng đã tới tay khách
 * và tiền COD đã được thu. Hỏi GHN = shipper xác nhận, chỉ khác là máy
 * hỏi thay vì người hỏi.
 *
 * ============================================================
 * BA LUẬT KHÔNG ĐƯỢC PHÁ:
 *
 *   1. CHỈ `delivered` MỚI ĐƯỢC ĐÁNH DẤU ĐÃ TRẢ TIỀN. `delivering`
 *      (shipper đang trên đường) KHÔNG phải là đã thu tiền — khách có
 *      quyền từ chối nhận ngay tại cửa.
 *
 *   2. KHÔNG TỰ HUỶ ĐƠN. GHN báo `cancel` / `returned` / `lost` thì ghi
 *      lại và để đó cho người xử lý: huỷ một đơn kéo theo hoàn kho, hoàn
 *      tiền, và có thể là một cuộc gọi cho khách. Máy làm thay quyết định
 *      đó là máy quyết chuyện tiền bạc thay người.
 *
 *   3. ĐI QUA OrderService, KHÔNG `update()` THẲNG. Đổi trạng thái đơn
 *      còn kéo theo gửi thư cho khách, ghi mốc thời gian, kiểm luật
 *      chuyển trạng thái. Ghi thẳng vào cột là bỏ qua tất cả — và khách
 *      không nhận được thư "đơn đã giao".
 */
class GhnStatusSync
{
    /**
     * Trạng thái GHN mà vận đơn đã đi hết đường — không cần hỏi lại nữa.
     *
     * Hỏi tiếp những vận đơn này chỉ tốn lượt gọi API cho một câu trả lời
     * không bao giờ đổi.
     */
    private const KET_THUC = ['delivered', 'returned', 'cancel', 'lost'];

    /**
     * Trạng thái GHN nghĩa là hàng đã rời cửa hàng và đang trên đường.
     *
     * `picked` trở đi: GHN đã cầm hàng trong tay. Trước đó
     * (`ready_to_pick`, `picking`) hàng vẫn ở cửa hàng, chưa gọi là "đang
     * giao" được.
     */
    private const DANG_GIAO = ['picked', 'storing', 'transporting', 'sorting', 'delivering'];

    public function __construct(
        private readonly GHNService $ghn,
        private readonly OrderService $orders,
        private readonly ActivityLogger $nhatKy,
    ) {
    }

    /**
     * Đồng bộ mọi vận đơn còn đang chạy.
     *
     * @return array{da_hoi: int, da_doi: int, loi: int}
     */
    public function syncAll(): array
    {
        $ketQua = ['da_hoi' => 0, 'da_doi' => 0, 'loi' => 0];

        if (! $this->ghn->configured()) {
            return $ketQua;
        }

        /*
         * `chunkById` chứ không `get()`: số vận đơn đang chạy có thể lớn
         * và mỗi vòng lặp còn gọi API + ghi CSDL. Nạp hết vào bộ nhớ rồi
         * mới chạy là cách hỏng khi cửa hàng bán được hàng.
         */
        Order::query()
            ->whereNotNull('ghn_order_code')
            ->where(fn ($q) => $q->whereNull('shipping_status')
                ->orWhereNotIn('shipping_status', self::KET_THUC))
            ->chunkById(50, function ($donHang) use (&$ketQua) {
                foreach ($donHang as $order) {
                    $ketQua['da_hoi']++;

                    try {
                        if ($this->syncOne($order)) {
                            $ketQua['da_doi']++;
                        }
                    } catch (\Throwable $e) {
                        /*
                         * MỘT ĐƠN HỎNG KHÔNG ĐƯỢC LÀM DỪNG CẢ LƯỢT.
                         *
                         * Đây là tác vụ chạy nền, không có ai ngồi nhìn.
                         * Ném ra ngoài là 49 đơn còn lại không được đồng
                         * bộ, và lần chạy sau lại chết đúng ở đơn đó.
                         */
                        $ketQua['loi']++;

                        Log::warning('Đồng bộ vận đơn GHN thất bại', [
                            'order_number' => $order->order_number,
                            'ghn_order_code' => $order->ghn_order_code,
                            'loi' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $ketQua;
    }

    /**
     * Đồng bộ một đơn. Trả về true nếu có gì đó thực sự thay đổi.
     */
    public function syncOne(Order $order): bool
    {
        $chiTiet = $this->ghn->orderDetail((string) $order->ghn_order_code);

        /*
         * GHNService::post() KHÔNG NÉM LỖI — nó trả về mảng có `code`
         * khác 200 và ghi log. Đó là lựa chọn đúng cho những nơi gọi
         * khác (tính phí hỏng thì hiện mức dự phòng, không làm sập trang
         * thanh toán), nhưng ở đây thì phải kiểm tay.
         *
         * LỖI ĐÃ SỬA: bản đầu chỉ đọc `data.status`, không thấy thì trả
         * về false. Nghĩa là GHN sập cả buổi mà lệnh vẫn báo "đã hỏi 40
         * vận đơn, 0 lỗi" — một hệ thống hỏng trông y hệt một hệ thống
         * không có gì để làm. Đó là kiểu hỏng tệ nhất: im lặng.
         *
         * Ném ra để syncAll() đếm vào `loi`, ghi log kèm mã đơn, và lệnh
         * thoát với mã lỗi cho cron nhìn thấy.
         */
        if ((int) ($chiTiet['code'] ?? 0) !== 200) {
            throw new \RuntimeException(sprintf(
                'GHN không trả lời được vận đơn %s: %s',
                $order->ghn_order_code,
                $chiTiet['message'] ?? 'không rõ lý do',
            ));
        }

        $trangThai = $chiTiet['data']['status'] ?? null;

        if (! is_string($trangThai) || $trangThai === '') {
            return false;
        }

        /*
         * GHN chưa đổi gì so với lần hỏi trước: không ghi, không log.
         * Ghi lại y nguyên giá trị cũ chỉ làm bẩn nhật ký — và nhật ký
         * đầy dòng vô nghĩa là nhật ký không ai đọc.
         */
        if ($trangThai === $order->shipping_status) {
            return false;
        }

        $cu = $order->shipping_status;
        $order->shipping_status = $trangThai;
        $order->save();

        $this->nhatKy->log(
            'don-hang.dong-bo-van-don',
            sprintf(
                'GHN cập nhật vận đơn %s: %s → %s',
                $order->ghn_order_code,
                $cu ?? '(chưa có)',
                $trangThai,
            ),
            $order,
            ['truoc' => $cu, 'sau' => $trangThai],
        );

        $this->theoTrangThaiVanDon($order, $trangThai);

        return true;
    }

    /**
     * Kéo trạng thái ĐƠN HÀNG theo trạng thái VẬN ĐƠN.
     *
     * Hai trục khác nhau và cố ý tách rời: vận đơn là chuyện của GHN, đơn
     * hàng là chuyện của cửa hàng. Hàm này là chỗ duy nhất nối chúng.
     */
    private function theoTrangThaiVanDon(Order $order, string $trangThai): void
    {
        if ($trangThai === 'delivered') {
            $this->daGiaoThanhCong($order);

            return;
        }

        if (in_array($trangThai, self::DANG_GIAO, true)) {
            $this->chuyenTrangThai($order, OrderStatus::Shipping);
        }

        /*
         * `cancel`, `returned`, `lost`, `delivery_fail`: CỐ Ý KHÔNG LÀM
         * GÌ ngoài việc đã ghi shipping_status ở trên. Xem luật 2 trong
         * chú thích đầu tệp — huỷ đơn là quyết định về tiền và về kho,
         * phải có người chịu trách nhiệm.
         */
    }

    /**
     * Hàng đã tới tay khách.
     *
     * HAI VIỆC, THEO ĐÚNG THỨ TỰ NÀY: đánh dấu đã thu tiền TRƯỚC, rồi mới
     * hoàn tất đơn.
     *
     * Vì sao thứ tự quan trọng: OrderService không cho chuyển sang "đã
     * thanh toán" trên một đơn đã huỷ, và luật quanh đơn đã hoàn tất chặt
     * hơn đơn đang giao. Làm ngược lại là tự dựng ra trạng thái "đã giao
     * nhưng chưa thu tiền" mà không có đường sửa tự động.
     */
    private function daGiaoThanhCong(Order $order): void
    {
        /*
         * CHỈ ĐƠN COD. Với một cổng thanh toán online thì tiền đã về từ
         * trước lúc giao, và người báo là cổng chứ không phải shipper —
         * đánh dấu lần nữa ở đây là ghi đè lên nguồn đúng hơn.
         */
        if ($order->payment_method === PaymentMethod::Cod
            && $order->payment_status === PaymentStatus::Unpaid) {

            $this->thu($order, fn () => $this->orders->setPaymentStatus($order, PaymentStatus::Paid));

            $this->nhatKy->log(
                'don-hang.tu-dong-thanh-toan',
                sprintf(
                    'Tự động ghi nhận đã thu tiền COD cho đơn %s (GHN báo đã giao thành công).',
                    $order->order_number,
                ),
                $order,
            );
        }

        $this->chuyenTrangThai($order, OrderStatus::Completed);
    }

    /**
     * Đưa đơn tới trạng thái đích, đi qua từng bước hợp lệ.
     *
     * VÌ SAO PHẢI ĐI TỪNG BƯỚC: OrderStatus chỉ cho chuyển sang trạng
     * thái liền kề (`Confirmed → Preparing → Shipping → Completed`). GHN
     * thì không biết gì về các bước đó — nó có thể nhảy thẳng từ lúc cửa
     * hàng vừa tạo vận đơn sang `delivered` nếu tác vụ đồng bộ không chạy
     * trong hai ngày.
     *
     * Ép thẳng sang đích là bỏ qua luật chuyển trạng thái, và bỏ qua luôn
     * những lá thư báo cho khách ở mỗi bước. Đi từng bước thì khách nhận
     * đủ thư và mốc thời gian trên đơn không bị khuyết.
     */
    private function chuyenTrangThai(Order $order, OrderStatus $dich): void
    {
        // Đã ở đích, hoặc đã đi hết đường (đơn bị huỷ) — không có gì làm.
        if ($order->status === $dich || $order->status->nextStates() === []) {
            return;
        }

        /*
         * Giới hạn số vòng: nếu vì lý do nào đó không tới được đích, vòng
         * lặp phải dừng thay vì quay mãi. Số bậc của OrderStatus đếm
         * được, nên lấy đúng số đó làm trần.
         */
        $conLai = count(OrderStatus::cases());

        while ($order->status !== $dich && $conLai-- > 0) {
            $buocTiep = $this->buocKeTiep($order->status);

            if ($buocTiep === null) {
                return;
            }

            /*
             * CHỤP TRẠNG THÁI TRƯỚC KHI GỌI, không phải sau.
             *
             * LỖI ĐÃ SỬA: changeStatus() sửa thẳng trên chính đối tượng
             * $order này, nên đọc $order->status sau lời gọi là đọc
             * trạng thái MỚI. Phép so "có nhúc nhích không" bên dưới khi
             * ấy luôn đúng bằng nhau, và vòng lặp thoát ngay sau bước
             * đầu tiên.
             *
             * Hậu quả đo được: đơn ở "Đã xác nhận" mà GHN báo đã giao
             * chỉ nhích lên "Đang chuẩn bị" rồi dừng — khách không nhận
             * được thư "đơn đã giao", và tiền COD thì đã ghi là đã thu.
             */
            $truoc = $order->status;

            $this->thu($order, fn () => $this->orders->changeStatus($order, $buocTiep));

            $order->refresh();

            // Không nhúc nhích (OrderService từ chối) thì dừng, đừng quay
            // tiếp cho hết số vòng.
            if ($order->status === $truoc) {
                return;
            }
        }
    }

    /**
     * Bước hợp lệ tiếp theo trên đường đi tới.
     *
     * Bỏ qua `Cancelled`: nó cũng là một trạng thái "kế tiếp" hợp lệ ở
     * hầu hết các bậc, nhưng nó không nằm trên đường tới `Completed` — đi
     * vào đó là tự huỷ đơn của khách.
     */
    private function buocKeTiep(OrderStatus $hienTai): ?OrderStatus
    {
        foreach ($hienTai->nextStates() as $ungVien) {
            if ($ungVien !== OrderStatus::Cancelled) {
                return $ungVien;
            }
        }

        return null;
    }

    /**
     * Chạy một thao tác của OrderService, nuốt lỗi nghiệp vụ.
     *
     * OrderException nghĩa là "trạng thái hiện tại không cho làm việc
     * này" — ví dụ admin vừa huỷ đơn ngay trước lượt đồng bộ. Đó không
     * phải sự cố, đó là cuộc đua bình thường giữa người và máy, và NGƯỜI
     * THẮNG: máy lùi lại, không ép.
     *
     * Chỉ nuốt đúng loại này. Lỗi CSDL hay lỗi mạng vẫn ném lên cho
     * syncAll() ghi log — nuốt hết là biến một hệ thống hỏng thành một hệ
     * thống im lặng.
     */
    private function thu(Order $order, callable $viec): void
    {
        try {
            $viec();
        } catch (OrderException $e) {
            Log::info('Bỏ qua một bước đồng bộ vận đơn', [
                'order_number' => $order->order_number,
                'ly_do' => $e->getMessage(),
            ]);
        }
    }
}
