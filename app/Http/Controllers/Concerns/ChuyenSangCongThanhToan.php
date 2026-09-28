<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Đưa khách sang cổng thanh toán ngoài (MoMo) QUA MỘT TRANG CHUYỂN TIẾP cùng tên miền.
 *
 * Không đi thẳng bằng redirect vì các nút "Đặt hàng", "Trả online" là BIỂU MẪU: chính sách
 * bảo mật của trang (form-action 'self') làm trình duyệt chặn lượt chuyển hướng từ biểu mẫu
 * ra tên miền khác — khách bấm xong thì màn hình đứng im, đơn vẫn được tạo.
 * Trả về trang chuyển tiếp thì lượt đi từ biểu mẫu kết thúc ngay trên tên miền của cửa hàng,
 * sau đó trang tự sang cổng như một lượt đi mới (không còn bị chặn), và khách nhìn thấy
 * "Đang chuyển tới MoMo…" thay vì tưởng nút hỏng.
 */
trait ChuyenSangCongThanhToan
{
    protected function chuyenSangCong(string $url, string $cong = 'MoMo', ?string $quayLai = null): View
    {
        return view('shop.payment.chuyen-cong', [
            'url' => $url,
            'cong' => $cong,
            'quayLai' => $quayLai,
        ]);
    }
}
