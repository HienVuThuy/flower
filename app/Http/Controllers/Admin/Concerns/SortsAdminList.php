<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Sắp xếp danh sách quản trị theo cột, an toàn với dữ liệu từ URL.
 * ============================================================
 * VẤN ĐỀ NGHIỆP VỤ: mọi trang danh sách đều có một thứ tự cố định do
 * lập trình viên chọn. Nhưng công việc thật không cố định:
 *
 *   - "Sản phẩm nào sắp hết hàng?"      → sắp theo tồn kho tăng dần
 *   - "Mã nào được dùng nhiều nhất?"    → sắp theo lượt dùng giảm dần
 *   - "Đơn nào giá trị lớn?"            → sắp theo tổng tiền giảm dần
 *
 * Không sắp được thì admin phải lật từng trang và tự nhớ — với 43 sản
 * phẩm còn làm được, với vài trăm thì không.
 *
 * DANH SÁCH TRẮNG BẮT BUỘC, KHÔNG NHẬN TÊN CỘT TỪ URL.
 *
 * `orderBy($request->query('sort'))` là một lỗ hổng: tên cột đi thẳng
 * vào câu SQL. Nhẹ thì lỗi 500 khi gõ bừa; nặng hơn là sắp theo cột
 * `password` để dò ký tự đầu của mã băm — chậm nhưng làm được.
 *
 * Mỗi trang tự khai cột nào sắp được, theo ánh xạ `khoá trên URL => cột
 * thật`. Khoá trên URL cũng là thứ người dùng nhìn thấy, nên đặt tên
 * tiếng Việt không dấu cho đồng bộ với phần còn lại của khu quản trị.
 */
trait SortsAdminList
{
    /**
     * Áp thứ tự sắp xếp, hoặc giữ nguyên thứ tự mặc định của trang.
     *
     * @param  array<string, string>  $choPhep  ['ten-tren-url' => 'cot_that']
     * @param  callable(Builder): void  $macDinh  thứ tự khi không sắp gì
     */
    protected function applySort(
        Builder $query,
        Request $request,
        array $choPhep,
        callable $macDinh,
    ): void {
        $khoa = (string) $request->query('sap');

        if (! array_key_exists($khoa, $choPhep)) {
            $macDinh($query);

            return;
        }

        /*
         * Hướng chỉ có đúng hai giá trị, và mặc định là TĂNG DẦN.
         *
         * Không dùng thẳng chuỗi từ URL vào câu lệnh, cùng lý do với tên
         * cột. So sánh với 'giam' rồi tự chọn một trong hai hằng số là
         * đủ — không có đường nào để chuỗi lạ lọt vào SQL.
         */
        $huong = $request->query('huong') === 'giam' ? 'desc' : 'asc';

        $query->orderBy($choPhep[$khoa], $huong);

        /*
         * CHỐT THÊM KHOÁ CHÍNH.
         *
         * Sắp theo cột có nhiều giá trị trùng nhau — trạng thái, tồn kho
         * — thì thứ tự giữa các hàng bằng nhau là KHÔNG XÁC ĐỊNH: cơ sở
         * dữ liệu được phép trả về khác nhau ở mỗi lần chạy. Với danh
         * sách có phân trang, điều đó nghĩa là một bản ghi có thể xuất
         * hiện ở cả trang 1 lẫn trang 2, hoặc không ở trang nào.
         *
         * Thêm `id` làm khoá phụ khiến thứ tự luôn xác định.
         */
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $huong);
    }
}
