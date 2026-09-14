<?php

namespace App\Services\Points;

use App\Enums\PointReason;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Time\Gio;
use Illuminate\Support\Facades\DB;

/**
 * Chuỗi ngày ghé thăm — "chi phí chìm": đã giữ được 6 ngày thì ngày thứ 7
 * người ta quay lại để không mất chuỗi.
 * ============================================================
 * GHÉ THĂM, KHÔNG PHẢI ĐĂNG NHẬP. Khách bật "ghi nhớ đăng nhập" thì không
 * bao giờ đăng nhập lại; đếm lần đăng nhập là phạt đúng người trung thành
 * nhất, và thưởng người đăng xuất ra vào cho đủ lượt. Mỗi trang khách mở
 * khi đang đăng nhập đều tính — một lần mỗi ngày.
 *
 * NGÀY THEO LỊCH VIỆT NAM. Theo giờ lưu (UTC) thì "ngày mới" bắt đầu lúc
 * 7 giờ sáng, và người ghé lúc 23 giờ rồi 1 giờ sáng hôm sau bị tính là
 * một ngày.
 *
 * CHỈ KHÁCH HÀNG. Nhân viên mở trang quản trị cả ngày — cho họ tích điểm
 * là trả lương bằng voucher.
 *
 * THƯỞNG Ở MỐC, không mỗi ngày: ngày thứ 3 và mỗi 7 ngày. Mỗi ngày một ít
 * thì không ai để ý; một mốc nhìn thấy trước mới là thứ kéo người quay lại.
 */
class VisitStreak
{
    public function __construct(
        private readonly PointLedger $so,
    ) {
    }

    /** Điểm thưởng khi chuỗi chạm đúng số ngày này; 0 nếu không phải mốc. */
    public static function moc(int $ngay): int
    {
        return match (true) {
            $ngay === 3 => 10,
            $ngay >= 7 && $ngay % 7 === 0 => 30,
            default => 0,
        };
    }

    /** @return array{ngay: int, diem: int} mốc kế tiếp sau chuỗi hiện tại */
    public static function mocTiepTheo(int $chuoi): array
    {
        $ngay = $chuoi + 1;

        while (self::moc($ngay) === 0) {
            $ngay++;
        }

        return ['ngay' => $ngay, 'diem' => self::moc($ngay)];
    }

    /**
     * Ghi nhận một lần ghé. Gọi ở mọi trang — rẻ khi đã ghi hôm nay.
     *
     * @return int|null số điểm vừa thưởng (chạm mốc), 0 nếu ghi nhận mà
     *                  chưa tới mốc, null nếu không ghi (đã ghi hôm nay, không phải khách)
     */
    public function ghiNhan(User $user): ?int
    {
        if ($user->role !== UserRole::Customer) {
            return null;
        }

        $homNay = now(Gio::mui())->toDateString();

        // Đường nhanh: mọi trang sau trang đầu tiên trong ngày dừng ở đây, không truy vấn.
        if ($user->last_visit_on?->toDateString() === $homNay) {
            return null;
        }

        $homQua = now(Gio::mui())->subDay()->toDateString();

        $chuoi = DB::transaction(function () use ($user, $homNay, $homQua) {
            /*
             * Đọc lại dưới khoá: hai tab mở cùng lúc lúc nửa đêm đều thấy
             * "chưa ghi hôm nay" ở đường nhanh. Không khoá thì chuỗi tăng 2.
             */
            $dong = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['visit_streak', 'last_visit_on']);

            if ($dong === null || $dong->last_visit_on === $homNay) {
                return null;
            }

            $moi = $dong->last_visit_on === $homQua ? (int) $dong->visit_streak + 1 : 1;

            // Không chạm updated_at: ghé thăm không phải "sửa tài khoản".
            DB::table('users')->where('id', $user->id)->update([
                'visit_streak' => $moi,
                'last_visit_on' => $homNay,
            ]);

            return $moi;
        });

        if ($chuoi === null) {
            $user->refresh();

            return null;
        }

        $user->forceFill(['visit_streak' => $chuoi, 'last_visit_on' => $homNay])->syncOriginal();

        $diem = self::moc($chuoi);

        if ($diem > 0) {
            $this->so->cong($user, $diem, PointReason::ChuoiNgay, 'chuoi:' . $homNay, 'Giữ chuỗi ' . $chuoi . ' ngày');
        }

        return $diem;
    }

    /**
     * Chuỗi còn sống — hôm nay hoặc hôm qua có ghé. Mất chuỗi thì 0, không
     * hiện con số cũ như thể nó vẫn còn.
     */
    public function hienTai(User $user): int
    {
        $ngay = $user->last_visit_on?->toDateString();

        return in_array($ngay, [now(Gio::mui())->toDateString(), now(Gio::mui())->subDay()->toDateString()], true)
            ? (int) $user->visit_streak
            : 0;
    }
}
