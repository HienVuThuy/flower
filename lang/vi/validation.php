<?php

/*
 * THÔNG BÁO KIỂM TRA DỮ LIỆU — TIẾNG VIỆT
 * ============================================================
 * :attribute được thay bằng tên trường. Muốn tên hiển thị thân thiện
 * (ví dụ "số điện thoại" thay vì "recipient_phone") thì khai ở mảng
 * 'attributes' cuối tệp, hoặc ở phương thức attributes() của FormRequest.
 *
 * Giữ ĐẦY ĐỦ danh sách quy tắc của Laravel thay vì chỉ dịch vài cái hay
 * dùng: thiếu khoá nào thì Laravel rơi về chuỗi tiếng Anh, và lỗi đó
 * chỉ lộ ra đúng lúc người dùng nhập sai — rất khó phát hiện khi test.
 */

return [

    'accepted' => 'Bạn phải đồng ý với :attribute.',
    'accepted_if' => 'Bạn phải đồng ý với :attribute khi :other là :value.',
    'active_url' => ':attribute không phải là một địa chỉ hợp lệ.',
    'after' => ':attribute phải là ngày sau :date.',
    'after_or_equal' => ':attribute phải là ngày :date trở đi.',
    'alpha' => ':attribute chỉ được chứa chữ cái.',
    'alpha_dash' => ':attribute chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
    'alpha_num' => ':attribute chỉ được chứa chữ cái và số.',
    'any_of' => ':attribute không hợp lệ.',
    'array' => ':attribute phải là một danh sách.',
    'array_keys' => ':attribute chỉ được chứa các khoá: :values.',
    'ascii' => ':attribute chỉ được chứa ký tự và ký hiệu một byte.',
    'base64' => ':attribute phải là chuỗi Base64 hợp lệ.',
    'before' => ':attribute phải là ngày trước :date.',
    'before_or_equal' => ':attribute phải là ngày :date trở về trước.',

    'between' => [
        'array' => ':attribute phải có từ :min đến :max phần tử.',
        'file' => ':attribute phải nặng từ :min đến :max kilobyte.',
        'numeric' => ':attribute phải nằm trong khoảng :min đến :max.',
        'string' => ':attribute phải dài từ :min đến :max ký tự.',
    ],

    'boolean' => ':attribute chỉ nhận giá trị đúng hoặc sai.',
    'can' => ':attribute chứa giá trị không được phép.',
    'confirmed' => ':attribute nhập lại không khớp.',
    'contains' => ':attribute thiếu một giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => ':attribute không phải là ngày hợp lệ.',
    'date_equals' => ':attribute phải đúng bằng ngày :date.',
    'date_format' => ':attribute không đúng định dạng :format.',
    'decimal' => ':attribute phải có :decimal chữ số thập phân.',
    'declined' => ':attribute phải được từ chối.',
    'declined_if' => ':attribute phải được từ chối khi :other là :value.',
    'different' => ':attribute và :other phải khác nhau.',
    'digits' => ':attribute phải gồm :digits chữ số.',
    'digits_between' => ':attribute phải gồm từ :min đến :max chữ số.',
    'dimensions' => ':attribute có kích thước ảnh không hợp lệ.',
    'distinct' => ':attribute bị trùng giá trị.',
    'doesnt_contain' => ':attribute không được chứa các giá trị: :values.',
    'doesnt_end_with' => ':attribute không được kết thúc bằng: :values.',
    'doesnt_start_with' => ':attribute không được bắt đầu bằng: :values.',
    'email' => ':attribute phải là địa chỉ email hợp lệ.',
    'encoding' => ':attribute phải được mã hoá theo :encoding.',
    'ends_with' => ':attribute phải kết thúc bằng một trong: :values.',
    'enum' => ':attribute được chọn không hợp lệ.',
    'exists' => ':attribute được chọn không tồn tại.',
    'extensions' => ':attribute phải có phần mở rộng: :values.',
    'file' => ':attribute phải là một tệp.',
    'filled' => ':attribute không được để trống.',

    'gt' => [
        'array' => ':attribute phải có nhiều hơn :value phần tử.',
        'file' => ':attribute phải nặng hơn :value kilobyte.',
        'numeric' => ':attribute phải lớn hơn :value.',
        'string' => ':attribute phải dài hơn :value ký tự.',
    ],

    'gte' => [
        'array' => ':attribute phải có từ :value phần tử trở lên.',
        'file' => ':attribute phải nặng từ :value kilobyte trở lên.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :value.',
        'string' => ':attribute phải dài từ :value ký tự trở lên.',
    ],

    'hex_color' => ':attribute phải là mã màu hex hợp lệ.',
    'image' => ':attribute phải là một tệp ảnh.',
    'in' => ':attribute được chọn không hợp lệ.',
    'in_array' => ':attribute không có trong :other.',
    'in_array_keys' => ':attribute phải chứa ít nhất một khoá: :values.',
    'integer' => ':attribute phải là số nguyên.',
    'ip' => ':attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => ':attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => ':attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => ':attribute phải là chuỗi JSON hợp lệ.',

    'lt' => [
        'array' => ':attribute phải có ít hơn :value phần tử.',
        'file' => ':attribute phải nhẹ hơn :value kilobyte.',
        'numeric' => ':attribute phải nhỏ hơn :value.',
        'string' => ':attribute phải ngắn hơn :value ký tự.',
    ],

    'lte' => [
        'array' => ':attribute không được nhiều hơn :value phần tử.',
        'file' => ':attribute phải nhẹ hơn hoặc bằng :value kilobyte.',
        'numeric' => ':attribute phải nhỏ hơn hoặc bằng :value.',
        'string' => ':attribute không được dài quá :value ký tự.',
    ],

    'list' => ':attribute phải là một danh sách.',
    'lowercase' => ':attribute phải viết thường toàn bộ.',
    'mac_address' => ':attribute phải là địa chỉ MAC hợp lệ.',

    'max' => [
        'array' => ':attribute không được nhiều hơn :max phần tử.',
        'file' => ':attribute không được nặng quá :max kilobyte.',
        'numeric' => ':attribute không được lớn hơn :max.',
        'string' => ':attribute không được dài quá :max ký tự.',
    ],

    'max_digits' => ':attribute không được nhiều hơn :max chữ số.',
    'mimes' => ':attribute phải là tệp thuộc loại: :values.',
    'mimetypes' => ':attribute phải là tệp thuộc loại: :values.',

    'min' => [
        'array' => ':attribute phải có ít nhất :min phần tử.',
        'file' => ':attribute phải nặng ít nhất :min kilobyte.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :min.',
        'string' => ':attribute phải dài ít nhất :min ký tự.',
    ],

    'min_digits' => ':attribute phải có ít nhất :min chữ số.',
    'missing' => ':attribute không được xuất hiện.',
    'missing_if' => ':attribute không được xuất hiện khi :other là :value.',
    'missing_unless' => ':attribute không được xuất hiện trừ khi :other là :value.',
    'missing_with' => ':attribute không được xuất hiện khi đã có :values.',
    'missing_with_all' => ':attribute không được xuất hiện khi đã có :values.',
    'multiple_of' => ':attribute phải là bội số của :value.',
    'not_in' => ':attribute được chọn không hợp lệ.',
    'not_regex' => ':attribute có định dạng không hợp lệ.',
    'numeric' => ':attribute phải là một số.',

    'password' => [
        'letters' => ':attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':attribute phải chứa cả chữ hoa và chữ thường.',
        'numbers' => ':attribute phải chứa ít nhất một chữ số.',
        'symbols' => ':attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':attribute đã từng bị lộ trong một vụ rò rỉ dữ liệu. Vui lòng chọn mật khẩu khác.',
    ],

    'present' => ':attribute phải được gửi lên.',
    'present_if' => ':attribute phải được gửi lên khi :other là :value.',
    'present_unless' => ':attribute phải được gửi lên trừ khi :other là :value.',
    'present_with' => ':attribute phải được gửi lên khi có :values.',
    'present_with_all' => ':attribute phải được gửi lên khi có :values.',
    'prohibited' => ':attribute không được phép.',
    'prohibited_if' => ':attribute không được phép khi :other là :value.',
    'prohibited_if_accepted' => ':attribute không được phép khi :other được chấp nhận.',
    'prohibited_if_declined' => ':attribute không được phép khi :other bị từ chối.',
    'prohibited_unless' => ':attribute không được phép trừ khi :other thuộc :values.',
    'prohibits' => ':attribute khiến :other không được phép xuất hiện.',
    'regex' => ':attribute có định dạng không hợp lệ.',
    'required' => 'Vui lòng nhập :attribute.',
    'required_array_keys' => ':attribute phải chứa các khoá: :values.',
    'required_if' => 'Vui lòng nhập :attribute khi :other là :value.',
    'required_if_accepted' => 'Vui lòng nhập :attribute khi :other được chấp nhận.',
    'required_if_declined' => 'Vui lòng nhập :attribute khi :other bị từ chối.',
    'required_unless' => 'Vui lòng nhập :attribute trừ khi :other thuộc :values.',
    'required_with' => 'Vui lòng nhập :attribute khi đã có :values.',
    'required_with_all' => 'Vui lòng nhập :attribute khi đã có :values.',
    'required_without' => 'Vui lòng nhập :attribute khi chưa có :values.',
    'required_without_all' => 'Vui lòng nhập :attribute khi chưa có :values.',
    'same' => ':attribute và :other phải giống nhau.',

    'size' => [
        'array' => ':attribute phải có đúng :size phần tử.',
        'file' => ':attribute phải nặng đúng :size kilobyte.',
        'numeric' => ':attribute phải bằng :size.',
        'string' => ':attribute phải dài đúng :size ký tự.',
    ],

    'starts_with' => ':attribute phải bắt đầu bằng một trong: :values.',
    'string' => ':attribute phải là chuỗi ký tự.',
    'timezone' => ':attribute phải là múi giờ hợp lệ.',
    'unique' => ':attribute đã được sử dụng.',
    'uploaded' => 'Tải :attribute lên không thành công.',
    'uppercase' => ':attribute phải viết hoa toàn bộ.',
    'url' => ':attribute phải là một địa chỉ hợp lệ.',
    'ulid' => ':attribute phải là ULID hợp lệ.',
    'uuid' => ':attribute phải là UUID hợp lệ.',

    /*
     * Thông báo riêng cho từng trường, dạng: 'trường.quy_tắc' => '...'
     * Dùng khi câu chung ở trên chưa đủ rõ với người dùng cuối.
     */
    'custom' => [
        'attribute-name' => [
            'rule-name' => 'thông báo tuỳ chỉnh',
        ],
        'password' => [
            'min' => 'Mật khẩu phải dài ít nhất :min ký tự.',
            'confirmed' => 'Mật khẩu nhập lại không khớp.',
        ],
    ],

    /*
     * Tên hiển thị của các trường dùng nhiều nơi.
     * FormRequest có attributes() riêng thì bản đó được ưu tiên.
     */
    'attributes' => [
        'name' => 'họ tên',
        'email' => 'email',
        'password' => 'mật khẩu',
        'password_confirmation' => 'xác nhận mật khẩu',
        'phone' => 'số điện thoại',
        'message' => 'nội dung',
        'quantity' => 'số lượng',
        'status' => 'trạng thái',
        'note' => 'ghi chú',
    ],

];
