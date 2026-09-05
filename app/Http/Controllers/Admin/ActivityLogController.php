<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Xem nhật ký thao tác quản trị.
 * ============================================================
 * CHỈ CÓ index(). Không có create, không có edit, KHÔNG CÓ destroy —
 * và đó không phải là thiếu sót:
 *
 * Nhật ký tồn tại để trả lời "ai đã làm việc này". Nếu người bị ghi có
 * thể xoá dòng ghi mình, câu trả lời đó không đáng tin nữa, và cả bảng
 * dữ liệu trở thành trang trí. Muốn dọn nhật ký cũ thì đó là việc bảo
 * trì cơ sở dữ liệu, không phải một nút trên màn hình.
 */
class ActivityLogController extends Controller
{
    /** Các nhóm việc lọc được, kèm nhãn tiếng Việt. */
    private const NHOM = [
        'order' => 'Đơn hàng',
        'product' => 'Sản phẩm',
        'category' => 'Danh mục',
        'coupon' => 'Mã giảm giá',
        'promotion' => 'Khuyến mại',
        'review' => 'Đánh giá',
        'user' => 'Tài khoản',
        'settings' => 'Cài đặt',
    ];

    public function index(Request $request): View
    {
        $logs = ActivityLog::query()
            ->with('user')

            /*
             * Lọc theo NHÓM chứ không theo từng mã việc.
             *
             * Người đi tìm không nhớ 'order.payment_changed'; họ nhớ
             * "có chuyện gì đó với đơn hàng". Danh sách thả xuống gồm
             * hai mươi mã việc là danh sách không ai dùng.
             */
            ->when(
                array_key_exists((string) $request->query('nhom'), self::NHOM),
                fn ($q) => $q->ofGroup((string) $request->query('nhom')),
            )

            ->when($request->filled('nguoi'), fn ($q) => $q
                ->where('user_id', $request->integer('nguoi')))

            /*
             * Tìm trong CÂU MÔ TẢ, vì đó là thứ người dùng nhìn thấy.
             * Cho tìm cả theo mã đơn hay tên sản phẩm mà không cần biết
             * dữ liệu được lưu ở cột nào.
             */
            ->when($request->filled('q'), fn ($q) => $q
                ->where('description', 'like', '%'.trim((string) $request->query('q')).'%'))

            ->when($request->filled('tu'), fn ($q) => $q
                ->whereDate('created_at', '>=', $request->date('tu')))
            ->when($request->filled('den'), fn ($q) => $q
                ->whereDate('created_at', '<=', $request->date('den')))

            // Mới nhất lên đầu: người mở nhật ký gần như luôn đang tìm
            // một việc vừa xảy ra.
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        /*
         * Danh sách người thực hiện lấy từ CHÍNH NHẬT KÝ, không lấy toàn
         * bộ tài khoản admin: lọc theo một người chưa từng thao tác lần
         * nào chỉ cho ra danh sách rỗng, và ô lọc đầy tên vô dụng.
         */
        $nguoiThucHien = User::query()
            ->whereIn('id', ActivityLog::query()->select('user_id')->whereNotNull('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'nhomViec' => self::NHOM,
            'nguoiThucHien' => $nguoiThucHien,
        ]);
    }
}
