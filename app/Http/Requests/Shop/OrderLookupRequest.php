<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Khách vãng lai tra cứu đơn bằng mã đơn + số điện thoại (hoặc email).
 *
 * Ở đây CHỈ kiểm tra hình thức dữ liệu nhập. Việc đơn có tồn tại hay
 * không, và số điện thoại có khớp hay không, do controller quyết định —
 * và luôn trả về CÙNG MỘT thông báo, để người ngoài không dò được đơn
 * nào có thật.
 */
class OrderLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Mã đơn viết hoa và bỏ khoảng trắng thừa: khách hay chép từ
            // email nên dễ dính dấu cách ở đầu/cuối.
            'order_number' => Str::upper(trim((string) $this->input('order_number'))),
            'contact' => trim((string) $this->input('contact')),
        ]);
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:32'],

            /*
             * Một ô nhập cho cả hai: khách không phải đoán xem lúc đặt
             * mình đã điền email hay chưa. Controller tự nhận dạng.
             */
            'contact' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'order_number' => 'mã đơn hàng',
            'contact' => 'số điện thoại hoặc email',
        ];
    }
}
