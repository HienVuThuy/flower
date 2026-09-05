<?php

namespace App\Http\Requests\Shop;

use App\Enums\PaymentMethod;
use App\Services\Shop\Provinces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bước 1 gộp: người nhận + giao hàng + thanh toán.
 * ============================================================
 * GỘP HAI FORM REQUEST CŨ (Recipient + Shipping) LÀM MỘT.
 *
 * Trước đây khách phải qua hai màn hình mới thấy được tổng tiền cuối
 * cùng: màn một nhập người nhận, bấm Tiếp, màn hai chọn giao hàng và
 * thanh toán. Nhưng phí giao phụ thuộc TỈNH — nhập ở màn một — nên tổng
 * tiền chỉ đúng từ màn hai trở đi. Khách điền xong màn một vẫn chưa biết
 * mình phải trả bao nhiêu, và đó là lúc nhiều người bỏ giỏ hàng.
 *
 * Gộp lại thì mọi thứ quyết định số tiền nằm trên CÙNG một màn hình, và
 * tóm tắt đơn bên cạnh luôn đúng.
 */
class CheckoutDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        /*
         * Khách chọn một địa chỉ trong sổ thì các ô nhập tay để trống,
         * nên không được bắt buộc. Dữ liệu thật khi đó lấy từ cơ sở dữ
         * liệu theo address_id (xem CheckoutController::storeDetails),
         * chính vì vậy ở đây KHÔNG cần validate nội dung các ô đó nữa.
         */
        $batBuoc = $this->filled('address_id') ? 'nullable' : 'required';

        return [
            /* ---------- người nhận ---------- */
            'address_id' => ['nullable', 'integer'],

            'recipient_name' => [$batBuoc, 'string', 'max:120'],
            // Số điện thoại Việt Nam: 10 số, bắt đầu bằng 0.
            'recipient_phone' => [$batBuoc, 'string', 'regex:/^0\d{9}$/'],
            'recipient_email' => ['nullable', 'email', 'max:160'],
            'shipping_address' => [$batBuoc, 'string', 'max:255'],
            'shipping_ward' => ['nullable', 'string', 'max:120'],
            'shipping_district' => ['nullable', 'string', 'max:120'],
            // Xem chú thích cùng quy tắc này trong AddressRequest.
            /*
             * TÊN TỈNH: ai là người nói nó có thật?
             * ============================================================
             * XUNG ĐỘT ĐO ĐƯỢC giữa hai nguồn:
             *
             *   App\Services\Shop\Provinces  →  34 tỉnh, tên SAU sáp nhập
             *                                     2025 ("Thành phố Hà Nội")
             *   Giao Hàng Nhanh               →  63 tỉnh, tên TRƯỚC sáp nhập
             *                                     ("Hà Nội", "Hòa Bình"...)
             *
             * Chỉ 28/63 mục trùng nhau. Giữ nguyên `Rule::in(...)` thì
             * khách chọn từ ô GHN xong bị báo "tỉnh/thành không hợp lệ" —
             * cho hơn nửa số tỉnh trong nước, và không có cách nào hiểu
             * vì sao.
             *
             * QUYẾT ĐỊNH: nguồn nào chứng minh được ĐỊA CHỈ GIAO ĐƯỢC thì
             * nguồn đó nói.
             *
             *   - Có mã GHN (`to_district_id` + `to_ward_code`) → tên tỉnh
             *     đến TỪ GHN. Chính GHN vừa xác nhận đó là nơi họ giao
             *     tới; đối chiếu lại với một danh sách tĩnh là để một bảng
             *     dữ liệu cũ hơn phủ quyết đơn vị vận chuyển.
             *   - Không có mã GHN (nhập tay dự phòng) → giữ nguyên
             *     `Rule::in(...)`, vì lúc đó không còn gì kiểm giúp và
             *     phí sẽ tra theo bảng vùng.
             *
             * KHÔNG dựng bảng ánh xạ 63 tên cũ sang 34 tên mới: bảng đó
             * phải sửa lại mỗi lần một trong hai bên đổi danh mục, và bên
             * bị quên sẽ lặng lẽ chặn mất một tỉnh.
             */
            'shipping_province' => [
                $batBuoc,
                'string',
                'max:120',
                Rule::when(
                    ! $this->filled('to_district_id') || ! $this->filled('to_ward_code'),
                    [Rule::in(Provinces::all())],
                ),
            ],

            /*
             * MÃ ĐỊA GIỚI GHN của nơi nhận hàng.
             *
             * nullable, KHÔNG bắt buộc — có ba tình huống hợp lệ mà
             * chúng vắng mặt:
             *
             *   - GHN đang trục trặc nên ô chọn không tải được danh mục;
             *   - khách tắt JavaScript nên ba ô chọn không chạy;
             *   - địa chỉ lấy từ sổ địa chỉ cũ, lưu trước khi có GHN.
             *
             * Cả ba đều không phải lý do để chặn một đơn hàng thật. Khi
             * thiếu, phí lùi về bảng theo tỉnh — xem ShippingQuote.
             *
             * KHÔNG nhận `shipping_fee` từ biểu mẫu, dù tài liệu hướng
             * dẫn có một ô ẩn như vậy. Trình duyệt gửi lên MÃ ĐỊA CHỈ,
             * máy chủ tự hỏi GHN ra tiền. Nhận số tiền từ client là mở
             * đường cho một dòng sửa trong DevTools thành giao miễn phí.
             */
            'to_district_id' => ['nullable', 'integer', 'min:1'],
            'to_ward_code' => ['nullable', 'string', 'max:20'],

            'save_address' => ['nullable', 'boolean'],

            /* ---------- giao hàng ---------- */
            // Không cho chọn ngày trong quá khứ.
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_note' => ['nullable', 'string', 'max:500'],

            /* ---------- thanh toán ---------- */
            /*
             * Rule::in(values()) chứ KHÔNG phải Rule::enum().
             *
             * Rule::enum() nhận MỌI case của enum, kể cả một cổng đã
             * khai case nhưng chưa cấu hình. Giao diện không hiện lựa
             * chọn đó, nhưng ai cũng sửa được gói tin gửi lên — và khi
             * ấy đơn được ghi với một hình thức thanh toán không chạy
             * được. values() chỉ trả về những hình thức dùng thật được.
             */
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
        ];
    }

    public function attributes(): array
    {
        return [
            'recipient_name' => 'tên người nhận',
            'recipient_phone' => 'số điện thoại',
            'recipient_email' => 'email',
            'shipping_address' => 'địa chỉ',
            'shipping_ward' => 'phường/xã',
            'shipping_district' => 'quận/huyện',
            'shipping_province' => 'tỉnh/thành phố',
            'to_district_id' => 'mã quận/huyện',
            'to_ward_code' => 'mã phường/xã',
            'delivery_date' => 'ngày giao',
            'delivery_note' => 'ghi chú',
            'payment_method' => 'hình thức thanh toán',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_phone.regex' => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.',
            'delivery_date.after_or_equal' => 'Ngày giao không thể là ngày đã qua.',
        ];
    }
}
