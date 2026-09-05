<?php

namespace App\Http\Requests;

use App\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkOrderInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'occasion' => ['nullable', 'string', 'max:150'],
            'quantity_estimate' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'message' => ['nullable', 'string', 'max:2000'],

            /*
             * CHI TIẾT SỰ KIỆN — TẤT CẢ ĐỀU KHÔNG BẮT BUỘC.
             *
             * Đây là điều kiện tiên quyết của cả nhóm ô này: biểu mẫu hỏi
             * nhiều mà bắt buộc hết thì khách bỏ ngang, và một phiếu
             * thiếu thông tin vẫn tốt hơn hẳn không có phiếu nào.
             */
            'company_name' => ['nullable', 'string', 'max:200'],

            /*
             * after_or_equal:today — sự kiện trong quá khứ chắc chắn là
             * gõ nhầm năm. Chặn ở đây rẻ hơn nhiều so với việc nhân viên
             * gọi lại hỏi "ý anh là năm sau đúng không".
             */
            'event_date' => ['nullable', 'date', 'after_or_equal:today'],
            'event_location' => ['nullable', 'string', 'max:255'],

            'budget_min' => ['nullable', 'integer', 'min:0', 'max:9999999999'],

            /*
             * gte:budget_min CHỈ ÁP KHI Ô "TỪ" CÓ ĐIỀN.
             *
             * Đo được: để 'gte:budget_min' vô điều kiện thì khách điền
             * mỗi "tối đa 5 triệu" bị báo lỗi "Ngân sách tối đa phải lớn
             * hơn hoặc bằng mức tối thiểu" — so với một ô trống. Nhưng
             * điền mỗi trần là hoàn toàn hợp lệ: khách biết mình có bao
             * nhiêu tiền mà chưa biết sàn.
             *
             * Bắt điền đủ cặp là bắt họ nghĩ ra một con số không có thật,
             * và đó là loại rào cản khiến người ta bỏ luôn biểu mẫu.
             */
            'budget_max' => array_merge(
                ['nullable', 'integer', 'min:0', 'max:9999999999'],
                $this->filled('budget_min') ? ['gte:budget_min'] : [],
            ),

            'color_preference' => ['nullable', 'string', 'max:150'],
            'flower_preference' => ['nullable', 'string', 'max:200'],

            'preferred_contact' => ['nullable', Rule::in(ContactChannel::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => 'Vui lòng nhập họ tên.',
            'contact_phone.required' => 'Vui lòng nhập số điện thoại.',
            'contact_phone.regex' => 'Số điện thoại không hợp lệ.',
            'contact_email.email' => 'Email không đúng định dạng.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'quantity_estimate.integer' => 'Số lượng phải là số nguyên.',
            'quantity_estimate.min' => 'Số lượng phải lớn hơn 0.',
            'message.max' => 'Nội dung không được vượt quá 2000 ký tự.',
            'event_date.after_or_equal' => 'Ngày sự kiện phải từ hôm nay trở đi.',
            'budget_max.gte' => 'Ngân sách tối đa phải lớn hơn hoặc bằng mức tối thiểu.',
            'preferred_contact.in' => 'Cách liên hệ không hợp lệ.',
        ];
    }
}
