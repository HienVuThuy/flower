<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationException;
use App\Services\Auth\EmailVerifier;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Xác thực email bằng mã OTP 6 chữ số.
 * ============================================================
 * KHÁC BÀI MẪU Ở ĐIỂM NÀO: bài mẫu gửi một LIÊN KẾT có chữ ký, ở đây
 * gửi MÃ 6 CHỮ SỐ. Lý do: khách hay mở email trên điện thoại rồi quay
 * lại gõ trên máy tính — bấm liên kết ở máy này thì phiên đăng nhập lại
 * nằm ở máy kia, và họ rơi vào trang đăng nhập giữa chừng.
 *
 * NHƯNG ĐƯỜNG LIÊN KẾT VẪN GIỮ (hàm verifyLink bên dưới). Nó gần như
 * miễn phí vì User đã implements MustVerifyEmail, và giữ lại thì bài
 * làm vẫn đúng nguyên yêu cầu của thầy — chỉ là mặc định dùng OTP.
 *
 * MỌI LUẬT nằm ở EmailVerifier. Controller này chỉ nhận request, gọi
 * dịch vụ, và dịch ngoại lệ thành thông báo trên trang.
 */
class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly EmailVerifier $verifier,
    ) {
    }

    /** Trang nhập mã. */
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('welcome'));
        }

        return view('auth.verify-email', [
            'email' => $user->email,
            'secondsUntilResend' => $this->verifier->secondsUntilResend($user),
            'expiresAt' => $this->verifier->expiresAt($user),
        ]);
    }

    /** Khách gõ mã 6 chữ số. */
    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(
            [
                /*
                 * digits:6 chứ không phải integer|max:999999.
                 *
                 * Mã "007355" là hợp lệ; ép sang số nguyên là mất số 0 ở
                 * đầu và mã đúng bị coi là sai. Đã đủ để một khách gọi
                 * lên tổng đài kêu "mã sai" mà không ai hiểu vì sao.
                 */
                'code' => ['required', 'digits:6'],
            ],
            [
                'code.required' => 'Vui lòng nhập mã xác thực.',
                'code.digits' => 'Mã xác thực gồm đúng 6 chữ số.',
            ],
        );

        try {
            $this->verifier->confirm($request->user(), $data['code']);
        } catch (EmailVerificationException $e) {
            /*
             * withInput() — GIỮ LẠI MÃ KHÁCH VỪA GÕ.
             *
             * Sai một chữ số trong sáu là chuyện thường. Xoá trắng ô thì
             * họ phải nhìn lại email và gõ lại cả sáu chữ; giữ nguyên thì
             * họ thấy mình gõ gì và sửa đúng chỗ sai.
             */
            return back()
                ->withErrors(['code' => $e->getMessage()])
                ->withInput();
        }

        /*
         * intended() — quay lại đúng chỗ khách đang muốn tới.
         *
         * Middleware `verified` đã lưu url.intended khi nó chặn họ. Ném
         * về trang chủ thì họ phải tự đi tìm lại chỗ cũ.
         */
        return redirect()
            ->intended(route('welcome'))
            ->with('success', 'Xác thực email thành công. Cảm ơn bạn!');
    }

    /** Gửi lại mã. */
    public function resend(Request $request): RedirectResponse
    {
        try {
            $this->verifier->send($request->user());
        } catch (EmailVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã gửi mã mới. Vui lòng kiểm tra hộp thư (kể cả mục Spam).');
    }

    /**
     * Đường xác thực bằng LIÊN KẾT — cách mặc định của Laravel.
     *
     * Giữ lại vì hai lý do: đúng yêu cầu của bài thực hành, và nếu khách
     * mở email ngay trên máy đang đăng nhập thì bấm một cái là xong,
     * không phải gõ gì.
     *
     * EmailVerificationRequest tự kiểm chữ ký, hạn của liên kết, và
     * kiểm rằng {id} đúng là người đang đăng nhập — không được tự viết
     * lại mấy phép kiểm đó.
     */
    public function verifyLink(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        // Mã OTP đang treo không còn nghĩa lý gì nữa.
        \App\Models\EmailVerificationCode::where('user_id', Auth::id())->delete();

        return redirect()
            ->intended(route('welcome'))
            ->with('success', 'Xác thực email thành công. Cảm ơn bạn!');
    }
}
