<?php

namespace App\Services\Checkout;

use App\Models\Order;

/**
 * Chống đặt trùng đơn hàng.
 * ============================================================
 * BA TÌNH HUỐNG PHẢI CHỊU ĐƯỢC, đều là chuyện xảy ra hằng ngày:
 *
 *  1. Khách bấm "Đặt hàng" hai lần vì lần đầu tưởng chưa ăn.
 *  2. Khách đặt xong, bấm Quay lại rồi gửi lại biểu mẫu.
 *  3. Mạng chậm, trình duyệt tự gửi lại request.
 *
 * CÁCH GIẢI: mỗi lượt thanh toán được cấp một KHOÁ dùng một lần. Khoá đi
 * theo phiên từ lúc khách bắt đầu điền thông tin cho tới khi đơn được
 * tạo, và được ghi thẳng vào cột orders.idempotency_key (UNIQUE).
 *
 * Nhờ vậy có HAI LỚP chặn, và lớp thứ hai mới là lớp thật sự chắc:
 *
 *   Lớp 1 (nhanh) — hỏi trước xem khoá này đã sinh ra đơn chưa. Xử lý
 *   gọn trường hợp tuần tự: bấm Quay lại, tải lại trang.
 *
 *   Lớp 2 (chắc) — ràng buộc UNIQUE của cơ sở dữ liệu. Hai request chạy
 *   song song đều có thể vượt qua lớp 1 vì cùng đọc thấy "chưa có đơn";
 *   chỉ cơ sở dữ liệu mới phân xử được ai thắng. Kẻ thua bắt lỗi trùng
 *   khoá rồi trả về chính đơn mà kẻ thắng vừa tạo.
 *
 * VÌ SAO KHÔNG DỰA VÀO KHOÁ PHIÊN CỦA LARAVEL:
 * Đo thực tế cho thấy SESSION_DRIVER=database đang vô tình xếp hàng các
 * request cùng phiên, nên hiện tại chưa sinh đơn trùng. Nhưng đó là tác
 * dụng phụ của trình điều khiển phiên, không phải điều dự án tự bảo đảm:
 * đổi sang driver không khoá (array, một số cấu hình redis) là mất ngay.
 * Và kể cả khi nó chặn được, khách bấm hai lần vẫn bị đẩy về trang giỏ
 * hàng kèm lỗi — tưởng đặt hỏng nên đặt lại lần nữa.
 */
class CheckoutGuard
{
    /** Khoá chống trùng của lượt thanh toán đang diễn ra. */
    public const KEY = 'checkout.idempotency';

    /**
     * Lấy khoá của lượt thanh toán hiện tại, chưa có thì cấp mới.
     *
     * Khoá phải SỐNG LÂU HƠN dữ liệu thanh toán trong phiên: sau khi đơn
     * được tạo, dữ liệu thanh toán bị dọn nhưng khoá thì giữ lại, để lần
     * bấm thứ hai còn tra ra được đơn vừa đặt mà đưa khách tới đó.
     */
    public function key(): string
    {
        $key = session(self::KEY);

        if (! is_string($key) || $key === '') {
            // 64 ký tự hex từ nguồn ngẫu nhiên an toàn: không đoán được,
            // và vừa đúng độ rộng cột.
            $key = bin2hex(random_bytes(32));
            session([self::KEY => $key]);
        }

        return $key;
    }

    /**
     * Bắt đầu một lượt thanh toán MỚI: vứt khoá cũ đi.
     *
     * Gọi ở bước nhập thông tin người nhận. Không gọi thì khách đặt đơn
     * thứ hai trong cùng phiên sẽ mang lại khoá của đơn thứ nhất, và bị
     * đá về đơn cũ thay vì đặt được đơn mới.
     */
    public function reset(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * Đơn đã được tạo từ khoá của phiên này, hoặc null.
     *
     * Đây là lớp 1 — nhanh và đủ cho trường hợp tuần tự.
     */
    public function existingOrder(): ?Order
    {
        $key = session(self::KEY);

        if (! is_string($key) || $key === '') {
            return null;
        }

        return Order::where('idempotency_key', $key)->first();
    }

    /**
     * Tra đơn theo khoá — dùng khi cơ sở dữ liệu vừa từ chối bản ghi
     * trùng, để biết đơn nào đã thắng.
     */
    public function orderFor(string $key): ?Order
    {
        return Order::where('idempotency_key', $key)->first();
    }
}
