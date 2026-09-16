<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\Notification\NotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Thông báo của khách. */
class NotificationController extends Controller
{
    public function index(NotificationCenter $tt): View
    {
        return view('shop.notifications.index', [
            'thongBao' => $tt->danhSach(Auth::user()),
            'soChuaDoc' => $tt->chuaDoc(Auth::user()),
        ]);
    }

    public function open(int $notification, NotificationCenter $tt): RedirectResponse
    {
        $tb = UserNotification::where('user_id', Auth::id())->findOrFail($notification);

        $tt->danhDauDaDoc($tb);

        return redirect()->to($tb->duongDan());
    }

    public function readAll(NotificationCenter $tt): RedirectResponse
    {
        $so = $tt->danhDauTatCa(Auth::user());

        return back()->with('success', $so > 0 ? 'Đã đánh dấu đã đọc ' . $so . ' thông báo.' : 'Không còn thông báo chưa đọc.');
    }
}
