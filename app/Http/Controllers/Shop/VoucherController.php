<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Trang voucher: xem mã đang mời, lưu về ví, xem ví của mình.
 * ============================================================
 * MỘT TRANG CHO CẢ HAI VIỆC, cố ý.
 *
 * Tách "voucher đang có" và "voucher của tôi" thành hai trang thì khách
 * phải nhớ mình đã lưu cái nào để khỏi đi tìm lại — mà đó chính là việc
 * tính năng này sinh ra để khỏi phải nhớ. Một trang, hai khối, mã đã lưu
 * nằm trên cùng.
 *
 * XEM ĐƯỢC KHI CHƯA ĐĂNG NHẬP, LƯU THÌ PHẢI ĐĂNG NHẬP.
 * Voucher là lý do để người ta tạo tài khoản; giấu hẳn sau màn đăng nhập
 * là bỏ mất chính tác dụng đó. Nút "Lưu" của khách vãng lai dẫn sang
 * trang đăng nhập rồi quay lại đúng chỗ cũ.
 */
class VoucherController extends Controller
{
    public function __construct(
        private readonly CouponWallet $wallet,
    ) {
    }

    public function index(): View
    {
        $user = Auth::user();

        /*
         * Mã đã ẩn chỉ nạp KHI KHÁCH BẤM XEM, không nạp sẵn mỗi lần mở
         * trang: phần lớn người dùng không có mã nào bị ẩn, và một truy
         * vấn thừa cho mọi lượt xem để phục vụ số ít là đổi sai chiều.
         */
        $xemDaAn = request()->boolean('da-an');
        $xemHetHan = request()->boolean('het-han');

        /*
         * MÃ HẾT HIỆU LỰC RỜI KHỎI VÍ, KHÔNG NẰM LẪN TRONG ĐÓ.
         *
         * Trước đây chúng vẫn hiện, chỉ chuyển xám. Ví dùng vài tháng là
         * đầy mã hết hạn, và mã còn dùng được lẫn vào giữa — đúng thứ ví
         * voucher sinh ra để khỏi phải lọc bằng mắt.
         *
         * KHÔNG XOÁ HÀNG DỮ LIỆU. Hàng trong `coupon_user` là bằng chứng
         * chống dùng quá suất; xoá đi là mở lại đúng lỗ hổng đó. Chúng
         * chỉ rời khỏi danh sách chính, và vẫn xem lại được.
         */
        $viDay = $user ? $this->wallet->forUser($user) : collect();

        [$conDung, $hetHieuLuc] = $viDay->partition(
            fn (array $row) => $this->wallet->conDungDuoc($row)
        );

        return view('shop.vouchers.index', [
            'mine' => $conDung->values(),
            'claimable' => $this->wallet->claimableFor($user),

            'hiddenCount' => $this->wallet->hiddenCount($user),
            'daAn' => $xemDaAn && $user ? $this->wallet->forUser($user, daAn: true) : collect(),
            'xemDaAn' => $xemDaAn,

            'hetHieuLucCount' => $hetHieuLuc->count(),
            'hetHieuLuc' => $xemHetHan ? $hetHieuLuc->values() : collect(),
            'xemHetHan' => $xemHetHan,
        ]);
    }

    /**
     * Trang điều kiện đầy đủ của một mã.
     * ============================================================
     * VÌ SAO PHẢI CÓ TRANG RIÊNG:
     * Thẻ voucher chỉ đủ chỗ cho mức giảm và một dòng điều kiện. Nhưng
     * mã thật luôn có một danh sách dài: hạn dùng, đơn tối thiểu, giảm
     * tối đa, giới hạn mỗi tài khoản, hình thức thanh toán, phạm vi sản
     * phẩm. Nhét hết vào thẻ thì không đọc được thẻ nào; giấu hết đi thì
     * khách bị từ chối ở bước thanh toán mà không hiểu vì sao.
     *
     * DÙNG TRANG RIÊNG, KHÔNG DÙNG POPUP JAVASCRIPT: nội dung dài, cần
     * cuộn, cần chia sẻ được bằng đường dẫn, và phải đọc được cả khi
     * JavaScript hỏng. Đây là điều khoản — thứ không được phép biến mất.
     *
     * CÔNG KHAI: khách phải đọc được điều kiện TRƯỚC khi quyết định lưu.
     * Bắt đăng nhập để xem điều kiện là đặt rào ngay chỗ họ đang cân nhắc.
     */
    public function show(Coupon $coupon): View
    {
        /*
         * Mã nội bộ (is_public = false) KHÔNG có trang điều kiện công
         * khai: nó được gửi riêng cho một khách, và trang này sẽ biến mọi
         * mã nội bộ thành mã ai cũng dò ra được bằng cách gõ mã vào URL.
         */
        abort_unless($coupon->is_public, 404);

        $user = Auth::user();

        /*
         * Số lần khách này đã dùng mã. Viết tách ra thay vì nhồi vào mảng
         * bên dưới: firstWhere() trả null khi mã chưa có trong ví, và
         * đọc chỉ số của null là cảnh báo PHP chứ không phải giá trị 0.
         */
        $row = $user
            ? $this->wallet->forUser($user)->firstWhere('coupon.id', $coupon->id)
            : null;

        return view('shop.vouchers.show', [
            'coupon' => $coupon->load('promotion'),
            'saved' => $row !== null,
            'usedCount' => (int) ($row['usedCount'] ?? 0),
        ]);
    }

    public function claim(Coupon $coupon): RedirectResponse
    {
        $user = Auth::user();

        try {
            $saved = $this->wallet->claim($user, $coupon);
        } catch (CouponException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $saved
            ? 'Đã lưu mã '.$coupon->code.' vào ví voucher.'
            // Bấm hai lần / hai tab. Không phải lỗi, nên không báo đỏ.
            : 'Mã '.$coupon->code.' vốn đã có trong ví của bạn.');
    }

    public function discard(Coupon $coupon): RedirectResponse
    {
        $ket = $this->wallet->discard(Auth::user(), $coupon);

        /*
         * BA KẾT QUẢ, BA CÂU KHÁC NHAU.
         *
         * Nói "đã xoá" cho một mã chỉ bị ẩn là nói sai — hàng dữ liệu vẫn
         * còn và giới hạn mỗi tài khoản vẫn tính. Khách cần biết đúng
         * chuyện gì vừa xảy ra, nhất là khi họ định lưu lại mã đó.
         */
        return match ($ket) {
            'deleted' => back()->with('success', 'Đã bỏ mã '.$coupon->code.' khỏi ví.'),
            'hidden' => back()->with(
                'success',
                'Đã ẩn mã '.$coupon->code.'. Mã đã dùng nên lượt sử dụng vẫn được giữ; '
                .'bạn xem lại được ở mục mã đã ẩn.',
            ),
            default => back()->with('error', 'Mã này không có trong ví của bạn.'),
        };
    }

    /** Đưa lại một mã đã ẩn về ví — đường quay lại cho nút ẩn. */
    public function unhide(Coupon $coupon): RedirectResponse
    {
        $ok = $this->wallet->unhide(Auth::user(), $coupon);

        return back()->with($ok ? 'success' : 'error', $ok
            ? 'Đã đưa mã '.$coupon->code.' trở lại ví.'
            : 'Mã này không nằm trong mục đã ẩn.');
    }
}
