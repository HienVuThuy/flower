<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Xem nhật ký thao tác quản trị. */
class ActivityLogController extends Controller
{
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

            ->when(
                array_key_exists((string) $request->query('nhom'), self::NHOM),
                fn ($q) => $q->ofGroup((string) $request->query('nhom')),
            )

            ->when($request->filled('nguoi'), fn ($q) => $q
                ->where('user_id', $request->integer('nguoi')))

            ->when($request->filled('q'), fn ($q) => $q
                ->where('description', 'like', '%'.trim((string) $request->query('q')).'%'))

            ->when($request->filled('tu'), fn ($q) => $q
                ->whereDate('created_at', '>=', $request->date('tu')))
            ->when($request->filled('den'), fn ($q) => $q
                ->whereDate('created_at', '<=', $request->date('den')))

            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

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
