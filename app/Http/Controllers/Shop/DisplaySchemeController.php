<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\DisplayScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Đổi chế độ sáng/tối.
 * ============================================================
 * MỘT ROUTE, CHẠY ĐƯỢC KHÔNG CẦN JAVASCRIPT.
 *
 * Có JavaScript thì nút đổi ngay tại chỗ rồi mới gửi lựa chọn về đây để
 * ghi cookie — người dùng không thấy trang tải lại. Không có JavaScript
 * thì biểu mẫu gửi bình thường và trang quay về đúng chỗ cũ, chỉ khác
 * màu nền. Cả hai đường đều đi qua đúng một chỗ ghi cookie.
 *
 * KHÔNG PHẢI GET. Đây là thao tác ghi (đặt cookie), mà GET thì trình
 * duyệt, trình quét và phần tải trước đều được phép tự gọi lại bất cứ
 * lúc nào — nghĩa là chế độ hiển thị có thể tự đổi mà người dùng không
 * hề bấm gì.
 */
class DisplaySchemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            /*
             * Chỉ nhận giá trị NGƯỜI DÙNG CHỌN ĐƯỢC (sáng/tối), không
             * nhận "auto": auto là trạng thái mặc định do máy chủ đặt,
             * không phải thứ ai đó gửi lên.
             */
            'che_do' => ['required', Rule::in(DisplayScheme::choices())],
        ]);

        /*
         * back() chứ không redirect tới một trang cố định: người dùng
         * bấm nút này ở giữa trang sản phẩm thì phải quay lại đúng trang
         * sản phẩm đó, không phải về trang chủ.
         */
        return back()->withCookie(DisplayScheme::cookie($data['che_do']));
    }
}
