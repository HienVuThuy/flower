<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Nền chung cho các thao tác hàng loạt ở khu quản trị.
 * ============================================================
 * NGHIỆP VỤ: hết mùa Tết thì có ba mươi sản phẩm phải chuyển sang tạm
 * ẩn. Làm từng cái là ba mươi lần bấm Sửa, cuộn tìm ô trạng thái, bấm
 * Lưu, chờ tải lại — và tới cái thứ mười lăm thì người làm bắt đầu bỏ
 * sót. Đó không phải sự bất tiện; đó là nguồn sinh lỗi dữ liệu.
 *
 * BA ĐIỀU BẮT BUỘC, và cả ba đều dễ quên khi viết vội:
 *
 * 1. VIỆC PHẢI NẰM TRONG DANH SÁCH TRẮNG. Nhận tên việc từ biểu mẫu rồi
 *    gọi thẳng thì bất kỳ ai cũng gửi được một việc không có trên màn
 *    hình. Đây là chỗ nguy hiểm hơn sắp xếp: sắp sai chỉ đảo thứ tự,
 *    còn việc hàng loạt thì GHI dữ liệu.
 *
 * 2. ID PHẢI ĐƯỢC KIỂM. Danh sách id đến từ các ô tích trong HTML, tức
 *    là từ client — sửa được bằng công cụ trình duyệt trong mười giây.
 *
 * 3. PHẢI NÓI RÕ ĐÃ LÀM CHO BAO NHIÊU BẢN GHI. "Đã cập nhật" mà không
 *    kèm con số thì người dùng không biết cả 30 sản phẩm đã đổi hay chỉ
 *    có 12 — và cách duy nhất để biết là tự đếm lại.
 */
trait HandlesBulkAction
{
    /**
     * Đọc và kiểm biểu mẫu hàng loạt.
     *
     * @param  array<int, string>  $viecChoPhep
     * @return array{viec: string, ids: array<int, int>}
     */
    protected function validateBulk(Request $request, array $viecChoPhep, string $bang): array
    {
        $data = $request->validate([
            'viec' => ['required', Rule::in($viecChoPhep)],

            'ids' => ['required', 'array', 'min:1'],

            /*
             * `exists` cho TỪNG id.
             *
             * Không kiểm thì một id không tồn tại lặng lẽ bị bỏ qua, và
             * con số báo về sẽ nhỏ hơn số ô đã tích — người dùng đếm
             * lại, thấy lệch, và không có gì giải thích.
             */
            'ids.*' => ['integer', Rule::exists($bang, 'id')],
        ], [
            'viec.required' => 'Hãy chọn một thao tác.',
            'viec.in' => 'Thao tác không hợp lệ.',
            'ids.required' => 'Hãy tích chọn ít nhất một dòng.',
            'ids.min' => 'Hãy tích chọn ít nhất một dòng.',
        ]);

        return [
            'viec' => $data['viec'],
            // array_unique: tích trùng id (biểu mẫu bị gửi lạ) không được
            // biến thành hai lần ghi lên cùng một bản ghi.
            'ids' => array_values(array_unique(array_map('intval', $data['ids']))),
        ];
    }
}
