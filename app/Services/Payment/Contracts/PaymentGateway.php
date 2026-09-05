<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;

/**
 * Hợp đồng chung của một cổng thanh toán trực tuyến.
 * ============================================================
 * CHƯA CÓ LỚP NÀO IMPLEMENTS. Đây là phần móng dựng trước cho MoMo
 * (dự kiến tuần sau), không phải một lớp trừu tượng dựng ra cho đẹp.
 *
 * VÌ SAO CÓ SẴN THAY VÌ CHỜ: viết lớp MoMo trước rồi mới rút ra hợp
 * đồng thì hợp đồng sẽ mang đúng hình dạng của MoMo, và cổng thứ hai
 * (VNPay, ZaloPay) sẽ phải bẻ mình cho vừa. Ba phương thức dưới đây là
 * ba việc MỌI cổng đều làm, không phải ba việc MoMo làm.
 *
 * ============================================================
 * BA VIỆC, VÀ MỘT LUẬT KHÔNG ĐƯỢC PHÁ:
 *
 *     khách bấm Đặt hàng
 *         │
 *         ├─► createPayment()  ──► đưa khách sang trang của cổng
 *         │
 *     (khách trả tiền ở đó)
 *         │
 *         └─► cổng gọi ngược về máy chủ mình
 *                 │
 *                 ├─► verifySignature()   sai chữ ký thì VỨT
 *                 └─► isSuccessful()      đúng thì mới đánh dấu đã trả
 *
 * LUẬT: đơn CHỈ được đánh dấu "đã thanh toán" từ lời gọi ngược của cổng,
 * sau khi chữ ký đã đúng. KHÔNG đánh dấu dựa vào việc trình duyệt khách
 * quay về trang "thành công" — đường quay về đó là một URL khách gõ tay
 * được, và bất kỳ ai cũng gõ được.
 *
 * Đó chính là điều làm cho thanh toán online là TỰ ĐỘNG, còn đối soát
 * chuyển khoản bằng mắt thì không.
 */
interface PaymentGateway
{
    /**
     * Mở một lượt thanh toán cho đơn hàng, trả về nơi cần đưa khách tới.
     *
     * Trả về URL tuyệt đối của cổng. Ném exception nếu cổng từ chối —
     * KHÔNG trả về chuỗi rỗng: nơi gọi sẽ chuyển hướng tới "" và khách
     * ở lại trang cũ mà không hiểu vừa xảy ra chuyện gì.
     */
    public function createPayment(Order $order): string;

    /**
     * Chữ ký của gói tin cổng gửi về có đúng không.
     *
     * Đây là RANH GIỚI TIN CẬY của cả luồng thanh toán. Bất kỳ ai trên
     * Internet cũng gửi được một request tới địa chỉ nhận callback; thứ
     * duy nhất phân biệt cổng thật với người giả mạo là chữ ký ký bằng
     * khoá bí mật chỉ hai bên biết.
     *
     * @param  array<string, mixed>  $payload  dữ liệu thô cổng gửi tới
     */
    public function verifySignature(array $payload): bool;

    /**
     * Gói tin ĐÃ XÁC MINH này có nghĩa là tiền đã về hay không.
     *
     * Tách khỏi verifySignature() vì hai câu hỏi khác nhau: "gói tin này
     * có thật không" và "nó nói gì". Cổng cũng gửi callback cho những
     * lượt thanh toán THẤT BẠI hoặc bị khách huỷ giữa chừng, và chữ ký
     * của chúng cũng đúng.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isSuccessful(array $payload): bool;

    /**
     * Mã đơn hàng của mình nằm ở đâu trong gói tin của cổng.
     *
     * Mỗi cổng đặt tên trường một kiểu (`orderId`, `vnp_TxnRef`...).
     * Không có hàm này thì nơi xử lý callback phải biết mình đang nói
     * chuyện với cổng nào — tức là hợp đồng chung không còn chung nữa.
     *
     * @param  array<string, mixed>  $payload
     */
    public function orderNumberFrom(array $payload): ?string;
}
