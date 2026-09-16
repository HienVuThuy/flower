<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CareReminder;
use App\Services\Care\CareScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** "Lịch chăm cây của tôi". */
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
            'notifyEnabled' => $user->notify_care_reminders !== false,
        ]);
    }

    public function toggle(Request $request, CareReminder $reminder): RedirectResponse
    {
        abort_unless($reminder->user_id === Auth::id(), 403);

        $reminder->update(['is_active' => ! $reminder->is_active]);

        return back()->with('success', $reminder->is_active
            ? 'Đã bật lại nhắc '.mb_strtolower($reminder->kind->label()).' cho '.($reminder->product?->name ?? 'cây này').'.'
            : 'Đã tắt nhắc '.mb_strtolower($reminder->kind->label()).' cho '.($reminder->product?->name ?? 'cây này').'.');
    }

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
