<?php

namespace App\Services\Points;

use App\Enums\CouponType;
use App\Enums\PointReason;
use App\Enums\PromotionStatus;
use App\Models\Coupon;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sổ điểm thưởng — NƠI DUY NHẤT cộng, trừ và đọc điểm.
 * ============================================================
 * VÌ SAO CÓ ĐIỂM, KHÔNG PHÁT THẲNG VOUCHER.
 *
 * Bài Góc cây, chuỗi ngày ghé thăm là những việc nhỏ, lặp lại. Mỗi việc
 * một voucher thì ví đầy mã lẻ tẻ 2.000đ không ai dùng, và cửa hàng không
 * kiểm soát được tổng tiền giảm. Điểm gom những việc nhỏ lại; khách tự
 * chọn lúc đổi — và chính việc thấy số điểm tăng dần là thứ giữ họ quay
 * lại.
 *
 * ============================================================
 * BA LUẬT.
 *
 *   1. MỘT VIỆC CHỈ ĐƯỢC CỘNG MỘT LẦN. Mỗi dòng mang `source_key` (ví dụ
 *      "bai:12"), duy nhất theo từng khách. Admin bấm duyệt hai lần, duyệt
 *      lại bài từng bị từ chối, hai tab cùng gửi — vẫn một dòng. Chặn bằng
 *      ràng buộc UNIQUE của cơ sở dữ liệu, không bằng "kiểm trước rồi ghi"
 *      (hai yêu cầu cùng lúc đều kiểm thấy chưa có).
 *
 *   2. KHÔNG ÂM. Đổi điểm khoá dòng người dùng rồi mới đọc số dư: hai lần
 *      bấm đổi cùng lúc với 250 điểm không được ra hai voucher 200 điểm.
 *
 *   3. VOUCHER ĐỔI ĐƯỢC LÀ CỦA RIÊNG NGƯỜI ĐỔI. Mã riêng tư, gắn chủ
 *      (`coupons.owner_user_id`), một lượt. Người khác biết mã cũng không
 *      dùng được — xem CouponService::check().
 */
class PointLedger
{
    /**
     * Các gói đổi điểm.
     *
     * Đây là CHÍNH SÁCH cửa hàng, không phải số liệu: 10 điểm ≈ 1.000đ, gói
     * lớn lời hơn một chút để khách có lý do tích thêm. Đơn tối thiểu giữ
     * cho voucher không biến thành tiền mặt ở một đơn 25.000đ.
     *
     * @var array<string, array{diem: int, giam: string, don_toi_thieu: string, ngay: int}>
     */
    public const GOI = [
        'giam-20k' => ['diem' => 200, 'giam' => '20000', 'don_toi_thieu' => '150000', 'ngay' => 30],
        'giam-50k' => ['diem' => 450, 'giam' => '50000', 'don_toi_thieu' => '300000', 'ngay' => 30],
    ];

    public function soDu(User $user): int
    {
        return (int) PointTransaction::where('user_id', $user->id)->sum('amount');
    }

    /**
     * @return Collection<int, PointTransaction>
     */
    public function lichSu(User $user, int $toiDa = 30): Collection
    {
        return PointTransaction::where('user_id', $user->id)
            ->latest('id')
            ->limit($toiDa)
            ->get();
    }

    /**
     * Cộng điểm cho một việc. Việc đó đã được cộng rồi thì không làm gì.
     *
     * @param  string  $khoa  định danh của việc, duy nhất theo khách ("bai:12")
     * @return bool true nếu vừa cộng, false nếu việc này đã được cộng từ trước
     */
    public function cong(User $user, int $diem, PointReason $lyDo, string $khoa, ?string $ghiChu = null): bool
    {
        if ($diem <= 0) {
            throw new \InvalidArgumentException('Chỉ cộng số điểm dương; trừ điểm đi qua tru() hoặc doiVoucher().');
        }

        return $this->ghi($user, $diem, $lyDo, $khoa, $ghiChu);
    }

    /**
     * Trừ điểm vì một việc (hoàn tiền cho đơn đã cộng điểm). Một việc một lần.
     *
     * ĐƯỢC PHÉP LÀM SỐ DƯ ÂM, khác với đổi voucher: điểm của đơn có thể đã
     * tiêu trước khi đơn được hoàn tiền. Chặn ở 0 là cho phép "mua, tích
     * điểm, đổi voucher, hoàn tiền" để lấy voucher miễn phí. Số dư âm thì
     * không đổi được gì cho tới khi tích lại.
     */
    public function tru(User $user, int $diem, PointReason $lyDo, string $khoa, ?string $ghiChu = null): bool
    {
        if ($diem <= 0) {
            throw new \InvalidArgumentException('Truyền số điểm cần trừ là số dương.');
        }

        return $this->ghi($user, -$diem, $lyDo, $khoa, $ghiChu);
    }

    /**
     * Trừ điểm khách dùng cho một đơn — GỌI TRONG TRANSACTION TẠO ĐƠN.
     *
     * Khoá dòng người dùng rồi đọc số dư: hai đơn đặt cùng lúc bằng cùng
     * một số điểm không được cùng qua. Không đủ thì ném — cuộn cả đơn.
     *
     * @throws PointException
     */
    public function dungChoDon(User $user, int $diem, \App\Models\Order $order): void
    {
        User::whereKey($user->id)->lockForUpdate()->first();

        if ($this->soDu($user) < $diem) {
            throw new PointException('Số điểm của bạn không còn đủ ' . number_format($diem, 0, ',', '.')
                . ' điểm — có thể vừa dùng ở nơi khác. Vui lòng chọn lại số điểm.');
        }

        $this->ghi($user, -$diem, PointReason::DungDiem, 'dung-diem:' . $order->id, 'Đơn ' . $order->order_number);
    }

    /**
     * Trả lại điểm đã dùng cho đơn (huỷ đơn, hoàn đủ tiền). Một đơn trả một lần.
     *
     * @return int số điểm vừa trả
     */
    public function traDiemCuaDon(\App\Models\Order $order): int
    {
        $diem = (int) $order->points_used;

        if ($diem <= 0 || $order->user_id === null || ($user = User::find($order->user_id)) === null) {
            return 0;
        }

        // Chỉ trả khi đơn thật sự đã trừ điểm.
        if (! PointTransaction::where('user_id', $user->id)->where('source_key', 'dung-diem:' . $order->id)->exists()) {
            return 0;
        }

        return $this->ghi($user, $diem, PointReason::HoanDiem, 'tra-diem:' . $order->id, 'Trả điểm đơn ' . $order->order_number)
            ? $diem
            : 0;
    }

    private function ghi(User $user, int $soDiem, PointReason $lyDo, string $khoa, ?string $ghiChu): bool
    {
        try {
            (new PointTransaction())->forceFill([
                'user_id' => $user->id,
                'amount' => $soDiem,
                'reason' => $lyDo,
                'source_key' => $khoa,
                'note' => $ghiChu !== null ? Str::limit($ghiChu, 190) : null,
            ])->save();

            return true;
        } catch (QueryException $e) {
            // Trùng (user_id, source_key) = việc này đã được cộng. Lỗi khác thì ném tiếp.
            if ($e->getCode() === '23000') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Đổi điểm lấy một voucher riêng.
     *
     * @throws PointException
     */
    public function doiVoucher(User $user, string $maGoi): Coupon
    {
        $goi = self::GOI[$maGoi] ?? throw new PointException('Gói đổi điểm không tồn tại.');

        return DB::transaction(function () use ($user, $goi) {
            // Khoá dòng người dùng: mọi lần đổi của cùng một khách xếp hàng qua đây.
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($this->soDu($user) < $goi['diem']) {
                throw new PointException('Bạn chưa đủ ' . $goi['diem'] . ' điểm để đổi gói này.');
            }

            $coupon = new Coupon([
                'code' => $this->sinhMa(),
                'name' => 'Đổi ' . $goi['diem'] . ' điểm',
                'description' => 'Voucher đổi từ điểm thưởng — chỉ tài khoản đã đổi dùng được.',
                'type' => CouponType::FixedAmount,
                'value' => $goi['giam'],
                'min_order_amount' => $goi['don_toi_thieu'],
                'usage_limit' => 1,
                'per_user_limit' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays($goi['ngay']),
                'status' => PromotionStatus::Active,
                'is_public' => false,
            ]);
            $coupon->forceFill(['owner_user_id' => $user->id])->save();

            // Vào thẳng ví: mã riêng không lưu được bằng nút "Lưu mã".
            DB::table('coupon_user')->insert([
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'claimed_at' => now(),
                'used_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            (new PointTransaction())->forceFill([
                'user_id' => $user->id,
                'amount' => -$goi['diem'],
                'reason' => PointReason::DoiVoucher,
                'source_key' => 'voucher:' . $coupon->id,
                'coupon_id' => $coupon->id,
                'note' => 'Mã ' . $coupon->code,
            ])->save();

            return $coupon;
        });
    }

    private function sinhMa(): string
    {
        do {
            // Không có chữ dễ nhầm (0/O, 1/I) — khách có thể phải gõ tay mã này.
            $ma = 'DIEM-' . substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 8);
        } while (Coupon::where('code', $ma)->exists());

        return $ma;
    }
}
