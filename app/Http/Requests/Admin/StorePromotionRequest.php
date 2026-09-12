<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));

        $this->merge([
            'name' => $name,
            'slug' => $this->filled('slug')
                ? Str::slug($this->input('slug'))
                : ($name !== '' ? Str::slug($name) : null),
        ]);

        /*
         * Ô `datetime-local` gửi lên GIỜ TRÊN ĐỒNG HỒ NGƯỜI GÕ, không kèm
         * múi giờ. Cất thẳng vào cột là cất giờ Hà Nội dưới nhãn UTC:
         * chương trình hẹn chạy 8h sáng sẽ chạy lúc 15h. Đổi ở đây, trước
         * khi kiểm tra, để `after_or_equal:starts_at` so hai mốc cùng múi.
         */
        $this->merge(\App\Services\Time\Gio::doiONhap($this->all(), 'starts_at', 'ends_at'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],

            'slug' => [
                'required', 'string', 'max:200',
                Rule::unique('promotions', 'slug'),
            ],

            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],

            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            'theme_key' => ['nullable', Rule::in(app(\App\Services\Theme\ThemeRegistry::class)->keys())],

            // Chỉ cho chọn kiểu đã thực sự tính được giá.
            'type' => [
                'required',
                Rule::in(array_map(fn ($t) => $t->value, PromotionType::selectable())),
            ],

            'discount_value' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            /*
             * GIÁ LINH HOẠT — chu kỳ lặp lại bên trong khoảng ngày.
             *
             * KHÔNG dùng 'after:daily_start_time' cho giờ kết thúc: khung
             * qua nửa đêm (22:00 → 02:00) là hợp lệ và rất phổ biến với
             * ưu đãi cuối ngày. Quy tắc đó sẽ chặn đúng trường hợp cần
             * dùng nhất. Model tự xử lý khung vòng qua nửa đêm — xem
             * Promotion::isWithinDailyWindow().
             */
            'daily_start_time' => ['nullable', 'date_format:H:i'],
            'daily_end_time' => ['nullable', 'date_format:H:i'],

            // 1 = Thứ Hai ... 7 = Chủ Nhật (ISO-8601).
            'weekdays' => ['nullable', 'array', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7'],

            'status' => [
                'required',
                Rule::in(array_column(PromotionStatus::cases(), 'value')),
            ],

            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Giảm theo % thì không thể vượt quá 100.
            if (
                $this->input('type') === PromotionType::Percent->value
                && (float) $this->input('discount_value') > 100
            ) {
                $validator->errors()->add(
                    'discount_value',
                    'Giảm theo phần trăm không được vượt quá 100%.'
                );
            }

            /*
             * Khung giờ phải khai ĐỦ HAI ĐẦU thì mới có tác dụng.
             *
             * Chỉ điền một ô là cấu hình dở dang: model coi như không
             * giới hạn và chương trình chạy cả ngày. Im lặng cho qua thì
             * admin tưởng đã đặt khung giờ, và chỉ phát hiện khi khách
             * mua được giá xả hàng lúc 9 giờ sáng.
             */
            $from = $this->input('daily_start_time');
            $to = $this->input('daily_end_time');

            if ((bool) $from !== (bool) $to) {
                $validator->errors()->add(
                    $from ? 'daily_end_time' : 'daily_start_time',
                    'Phải điền cả giờ bắt đầu và giờ kết thúc, hoặc bỏ trống cả hai.'
                );
            }

            if ($from && $to && $from === $to) {
                // Bắt đầu bằng kết thúc: nếu để lọt thì
                // isWithinDailyWindow() chỉ đúng đúng một giây trong ngày
                // — gần như là chương trình không bao giờ chạy.
                $validator->errors()->add(
                    'daily_end_time',
                    'Giờ kết thúc phải khác giờ bắt đầu.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên chương trình.',
            'slug.required' => 'Không thể tạo slug cho chương trình.',
            'slug.unique' => 'Slug này đã được dùng cho chương trình khác.',
            'banner.image' => 'Banner phải là hình ảnh.',
            'banner.mimes' => 'Banner phải có định dạng JPG, JPEG, PNG hoặc WEBP.',
            'banner.max' => 'Banner không được lớn hơn 4MB.',
            'theme_key.in' => 'Theme không hợp lệ.',
            'type.required' => 'Vui lòng chọn hình thức khuyến mại.',
            'type.in' => 'Hình thức khuyến mại không hợp lệ.',
            'discount_value.required' => 'Vui lòng nhập mức giảm.',
            'discount_value.numeric' => 'Mức giảm phải là số.',
            'discount_value.min' => 'Mức giảm không được âm.',
            'ends_at.after_or_equal' => 'Ngày kết thúc phải sau ngày bắt đầu.',
            'daily_start_time.date_format' => 'Giờ bắt đầu phải theo dạng HH:MM, ví dụ 19:00.',
            'daily_end_time.date_format' => 'Giờ kết thúc phải theo dạng HH:MM, ví dụ 22:00.',
            'weekdays.*.between' => 'Thứ trong tuần không hợp lệ.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'priority.required' => 'Vui lòng nhập độ ưu tiên.',
        ];
    }
}
