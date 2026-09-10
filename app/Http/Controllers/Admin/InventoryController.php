<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\InventoryReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang Tồn kho.
 * ============================================================
 * TÁCH KHỎI TRANG SẢN PHẨM, có chủ ý.
 *
 * Trang Sản phẩm trả lời "cửa hàng bán những gì" — nó là nơi sửa tên,
 * giá, ảnh. Trang này trả lời một câu khác hẳn: "phải nhập gì, phải bỏ
 * gì, tiền đang nằm ở đâu". Nhồi cả hai vào một bảng thì cột nào cũng
 * có mà không câu nào trả lời được.
 */
class InventoryController extends Controller
{
    /** Các khoảng dùng để tính tốc độ bán. */
    private const KY = [
        '7' => '7 ngày qua',
        '30' => '30 ngày qua',
        '90' => '90 ngày qua',
    ];

    public function index(Request $request, InventoryReport $report): View
    {
        $ky = (string) $request->query('ky', '30');

        if (! array_key_exists($ky, self::KY)) {
            $ky = '30';
        }

        $report->trongVong((int) $ky);

        /*
         * NGƯỠNG "SẮP HẾT" TÍNH BẰNG NGÀY, không bằng số lượng.
         *
         * 14 ngày là khoảng thời gian đủ để đặt hàng và nhận về với hoa
         * và cây cảnh trong nước. Cho admin đổi được vì mỗi cửa hàng có
         * một nhà cung cấp khác nhau.
         */
        $nguong = (float) $request->query('nguong', 14);
        $nguong = max(1, min(90, $nguong));

        return view('admin.inventory.index', [
            'ky' => $ky,
            'cacKy' => self::KY,
            'nguong' => $nguong,

            'tongQuan' => $report->tongQuan(),
            'sapHet' => $report->sapHet($nguong),
            'daHet' => $report->daHet(),
            'chetVon' => $report->chetVon(),
        ]);
    }
}
