<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\OrderLookupRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * TRA CỨU ĐƠN CHO KHÁCH VÃNG LAI (Guide §XI).
 * ============================================================
 * Khách đặt hàng không cần tài khoản. Đặt xong, đóng trình duyệt là
 * mất dấu đơn — session hết hạn thì chính họ cũng không xem lại được
 * đơn của mình. Trang này giải quyết đúng chuyện đó.
 *
 * BA ĐIỀU PHẢI GIỮ, vì đây là cửa duy nhất cho người chưa đăng nhập
 * đọc tên, số điện thoại và địa chỉ nhà của một người:
 *
 * 1. KHÔNG nhận thông tin liên hệ qua URL. Chỉ POST. Số điện thoại nằm
 *    trên thanh địa chỉ sẽ đi vào lịch sử trình duyệt, log máy chủ và
 *    header Referer gửi sang website khác.
 *
 * 2. MỌI trường hợp thất bại trả về CÙNG MỘT câu. Nếu "không có đơn
 *    này" khác với "sai số điện thoại" thì người ngoài dò được mã đơn
 *    nào có thật, rồi mới tấn công tiếp vào số điện thoại.
 *
 * 3. Route có `throttle` — xem routes/web.php. Mã đơn chỉ có 4 ký tự
 *    ngẫu nhiên; không giới hạn số lần thử thì dò được.
 *
 * Tra đúng rồi thì KHÔNG dựng trang xem đơn riêng: cấp quyền cho phiên
 * hiện tại rồi chuyển sang đúng trang đơn hàng đã có. Một trang, một
 * chỗ kiểm tra quyền — thêm bản sao thứ hai là thêm chỗ để quên.
 */
class OrderLookupController extends Controller
{
    /**
     * Số mã đơn tối đa giữ trong session.
     *
     * Không có giới hạn thì mỗi lần tra cứu thành công lại nhét thêm
     * một mã, session phình ra vô hạn.
     */
    private const MAX_REMEMBERED = 20;

    public function form(): View
    {
        return view('shop.orders.lookup');
    }

    public function find(OrderLookupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = $this->match($data['order_number'], $data['contact']);

        if (! $order) {
            /*
             * Một câu duy nhất cho: mã sai, đơn không tồn tại, đơn đã
             * xoá, liên hệ không khớp. Xem ghi chú (2) ở đầu lớp.
             */
            return back()
                ->withInput($request->only('order_number'))
                ->withErrors([
                    'order_number' => 'Không tìm thấy đơn hàng khớp với thông tin bạn nhập. '
                        . 'Vui lòng kiểm tra lại mã đơn và số điện thoại đã dùng khi đặt.',
                ]);
        }

        $this->remember($order);

        return redirect()->route('shop.orders.show', $order);
    }

    /**
     * Tìm đơn khớp CẢ mã lẫn thông tin liên hệ.
     *
     * Chỉ một truy vấn, và điều kiện liên hệ nằm ngay trong truy vấn —
     * không lấy đơn ra rồi mới so trong PHP, để không có nhánh nào lỡ
     * trả về đơn khi liên hệ sai.
     */
    private function match(string $orderNumber, string $contact): ?Order
    {
        $digits = preg_replace('/\D/', '', $contact);

        /*
         * Dựng danh sách giá trị liên hệ được chấp nhận TRƯỚC.
         *
         * Nếu ghép điều kiện thẳng vào một closure `where(...)`, khách gõ
         * chuỗi không có chữ số và không có "@" (ví dụ "abc") sẽ tạo ra
         * một nhóm điều kiện RỖNG — và truy vấn khi đó khớp mọi đơn có
         * mã đó, tức là mở đơn mà không cần biết số điện thoại.
         */
        $candidates = [];

        // Số điện thoại lưu dạng 0xxxxxxxxx; khách có thể gõ
        // "0901 112 223" hoặc "+84901112223".
        if ($digits !== '') {
            $candidates[] = $digits;

            if (str_starts_with($digits, '84')) {
                $candidates[] = '0' . substr($digits, 2);
            }
        }

        // Email chỉ khớp khi lúc đặt khách có điền. Đơn không có email
        // thì nhánh này không bao giờ đúng.
        $email = str_contains($contact, '@') ? $contact : null;

        if (! $candidates && ! $email) {
            return null;
        }

        return Order::query()
            ->where('order_number', $orderNumber)
            ->where(function ($q) use ($candidates, $email) {
                if ($candidates) {
                    $q->orWhereIn('recipient_phone', $candidates);
                }

                if ($email) {
                    $q->orWhere('recipient_email', $email);
                }
            })
            ->first();
    }

    /**
     * Cho phiên hiện tại xem đơn này.
     *
     * Dùng chung khoá với lúc vừa đặt hàng, nên Shop\OrderController
     * không phải biết đơn được mở ra bằng cách nào.
     */
    private function remember(Order $order): void
    {
        // Khách đã đăng nhập và đơn là của họ thì OrderController cho
        // xem sẵn rồi, không cần ghi thêm vào session.
        if (Auth::check() && $order->user_id === Auth::id()) {
            return;
        }

        $placed = session(CheckoutController::PLACED_KEY, []);

        if (! in_array($order->order_number, $placed, strict: true)) {
            $placed[] = $order->order_number;
        }

        session([
            CheckoutController::PLACED_KEY => array_slice($placed, -self::MAX_REMEMBERED),
        ]);
    }
}
