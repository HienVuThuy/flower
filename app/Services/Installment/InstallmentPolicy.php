<?php

namespace App\Services\Installment;

use App\Enums\InstallmentStatus;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Services\Credit\CreditScore;
use App\Services\Shop\Money;

/** Ai được trả góp, được mấy kỳ, trả trước bao nhiêu. */
class InstallmentPolicy
{
    public function __construct(
        private readonly CreditScore $tinDung,
    ) {
    }

    public function xet(?User $user, string $tongDon): array
    {
        $ket = ['duoc' => false, 'ly_do' => null, 'diem' => null, 'muc' => null, 'ky_toi_da' => 0, 'tra_truoc' => 0];

        if (! InstallmentSettings::bat()) {
            return ['ly_do' => 'Cửa hàng chưa mở trả góp.'] + $ket;
        }

        if ($user === null) {
            return ['ly_do' => 'Đăng nhập để trả góp — cửa hàng xét trả góp theo lịch sử thanh toán của tài khoản.'] + $ket;
        }

        if (bccomp($tongDon, InstallmentSettings::donToiThieu(), 2) < 0) {
            return ['ly_do' => 'Trả góp áp dụng cho đơn từ ' . Money::format(InstallmentSettings::donToiThieu()) . '.'] + $ket;
        }

        if (InstallmentPlan::query()->where('user_id', $user->id)->where('status', InstallmentStatus::DangTra->value)->exists()) {
            return ['ly_do' => 'Bạn đang có một kế hoạch trả góp chưa trả xong. Trả xong kế hoạch đó rồi mới mở kế hoạch mới.'] + $ket;
        }

        $diem = $this->tinDung->cua($user)['diem'];
        $ket['diem'] = $diem;
        $toiThieu = InstallmentSettings::soNguyen('tra_gop.diem_toi_thieu');

        if ($diem < $toiThieu) {
            return ['ly_do' => sprintf('Điểm tín dụng của bạn là %d, cần từ %d để trả góp.', $diem, $toiThieu)] + $ket;
        }

        $tot = $diem >= InstallmentSettings::soNguyen('tra_gop.diem_tot');

        return [
            'duoc' => true,
            'ly_do' => null,
            'diem' => $diem,
            'muc' => $tot ? 'tot' : 'thuong',
            'ky_toi_da' => InstallmentSettings::soNguyen($tot ? 'tra_gop.ky_toi_da_tot' : 'tra_gop.ky_toi_da_thuong'),
            'tra_truoc' => InstallmentSettings::soNguyen($tot ? 'tra_gop.tra_truoc_tot' : 'tra_gop.tra_truoc_thuong'),
        ];
    }
}
