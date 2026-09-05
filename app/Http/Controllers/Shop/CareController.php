<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CareReminder;
use App\Services\Care\CareScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Lịch chăm cây của tôi".
 * ============================================================
 * Trang này là mặt nhìn thấy được của một tính năng chạy ngầm. Không có
 * nó thì khách chỉ biết tới lịch nhắc qua những lá thư tự đến — không
 * biết mình đang có bao nhiêu lịch, không biết cây nào sắp tới hạn, và
 * muốn tắt thì phải đợi thư tới rồi mới có đường vào.
 *
 * MỌI THAO TÁC ĐỀU PHẢI KIỂM TRA CHỦ SỞ HỮU. Id lịch đi qua biểu mẫu nên
 * người gửi sửa được thành bất kỳ số nào; thiếu điều kiện user_id thì ai
 * cũng tắt được lịch của người khác chỉ bằng cách đoán đúng một id.
 */
class CareController extends Controller
{
    public function __construct(
        private readonly CareScheduler $scheduler,
    ) {
    }

    public function index(): View
    {
        $user = Auth::user();

        return view('shop.care.index', [
            'reminders' => $this->scheduler->forUser($user),
            // Công tắc tổng — tắt nó thì mọi lịch đều im, kể cả lịch đang bật.
            'notifyEnabled' => $user->notify_care_reminders !== false,
        ]);
    }

    /**
     * Bật / tắt một lịch cụ thể.
     *
     * TẮT chứ không XOÁ (xem migration): xoá thì lần sau khách mua lại
     * đúng cây đó, lịch được tạo mới và họ phải tắt lại lần nữa.
     */
    public function toggle(Request $request, CareReminder $reminder): RedirectResponse
    {
        /*
         * Không dùng Policy cho một điều kiện duy nhất này.
         *
         * abort_unless nằm ngay đây thì đọc hàm là thấy luật; tách sang
         * một lớp Policy riêng cho đúng một phép so sánh id là thêm một
         * tệp để người sau phải mở ra mới biết chuyện gì đang xảy ra.
         */
        abort_unless($reminder->user_id === Auth::id(), 403);

        $reminder->update(['is_active' => ! $reminder->is_active]);

        return back()->with('success', $reminder->is_active
            ? 'Đã bật lại nhắc '.mb_strtolower($reminder->kind->label()).' cho '.($reminder->product?->name ?? 'cây này').'.'
            : 'Đã tắt nhắc '.mb_strtolower($reminder->kind->label()).' cho '.($reminder->product?->name ?? 'cây này').'.');
    }

    /**
     * Đánh dấu "tôi vừa làm xong" — dời hạn sang kỳ tiếp theo.
     *
     * VÌ SAO CẦN: khách tưới sớm hai ngày thì lịch vẫn nhắc theo mốc cũ,
     * và lời nhắc đó sai. Nút này để họ nói cho hệ thống biết, thay vì
     * phải chịu một lời nhắc thừa rồi tự bỏ qua.
     */
    public function done(CareReminder $reminder): RedirectResponse
    {
        abort_unless($reminder->user_id === Auth::id(), 403);

        $reminder->advance();

        return back()->with('success', sprintf(
            'Đã ghi nhận. Lần %s tiếp theo: %s.',
            mb_strtolower($reminder->kind->label()),
            $reminder->next_due_at->format('d/m/Y'),
        ));
    }
}
