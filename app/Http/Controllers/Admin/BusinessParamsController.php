<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Services\Shop\Money;
use App\Services\Shop\ThamSoKinhDoanh;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** CÀI ĐẶT › THAM SỐ KINH DOANH — ngưỡng, phí, điểm, hạn mà chủ cửa hàng tự chỉnh. */
class BusinessParamsController extends Controller
{
    use LogsAdminActivity;

    public function edit(): View
    {
        return view('admin.settings.tham-so', [
            'nhom' => ThamSoKinhDoanh::nhom(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $moi = [];
        $loi = [];

        foreach (ThamSoKinhDoanh::DS as $khoa => $dn) {
            $o = self::tenO($khoa);
            $tho = $request->input("ts.$o");
            $tho = is_string($tho) ? trim($tho) : '';

            if ($tho === '') {
                $moi[$khoa] = null;

                continue;
            }

            if ($dn['kieu'] === 'moc_tien') {
                $moc = ThamSoKinhDoanh::docMoc($tho);

                if ($moc === null) {
                    $loi["ts.$o"] = 'Nhập từ 1 đến 6 mốc tiền, cách nhau bằng dấu phẩy.';
                } elseif ($moc[0] < $dn['min'] || end($moc) > $dn['max']) {
                    $loi["ts.$o"] = 'Mỗi mốc phải từ ' . Money::format($dn['min']) . ' đến ' . Money::format($dn['max']) . '.';
                } else {
                    $moi[$khoa] = $moc;
                }

                continue;
            }

            $so = preg_replace('/\D/', '', $tho);

            if ($so === '' || preg_match('/[^\d.,\s₫đ]/u', $tho)) {
                $loi["ts.$o"] = 'Phải là số không âm.';

                continue;
            }

            $so = (int) $so;

            if ($so < $dn['min'] || $so > $dn['max']) {
                $loi["ts.$o"] = 'Phải từ ' . self::hien($dn, $dn['min']) . ' đến ' . self::hien($dn, $dn['max']) . '.';

                continue;
            }

            $moi[$khoa] = $so;
        }

        if ($loi !== []) {
            throw ValidationException::withMessages($loi);
        }

        $doi = [];

        foreach ($moi as $khoa => $giaTri) {
            $truoc = ThamSoKinhDoanh::giaTri($khoa);
            ThamSoKinhDoanh::luu($khoa, $giaTri);
            $sau = ThamSoKinhDoanh::giaTri($khoa);

            if ($truoc != $sau) {
                $doi[$khoa] = ['truoc' => $truoc, 'sau' => $sau];
            }
        }

        if ($doi !== []) {
            $this->audit()->log('settings.params', 'Đổi ' . count($doi) . ' tham số kinh doanh', null, $doi);
        }

        return redirect()
            ->route('admin.business-params.edit')
            ->with('success', $doi === [] ? 'Không có tham số nào thay đổi.' : 'Đã lưu ' . count($doi) . ' tham số. Áp dụng ngay cho mọi trang.');
    }

    /** Tên ô nhập: dấu chấm trong khoá bị Laravel hiểu là mảng lồng nhau nên đổi thành "__". */
    public static function tenO(string $khoa): string
    {
        return str_replace('.', '__', $khoa);
    }

    public static function hien(array $dn, mixed $giaTri): string
    {
        return match ($dn['kieu']) {
            'tien' => Money::format($giaTri),
            'moc_tien' => implode(', ', array_map(fn ($m) => Money::number($m), (array) $giaTri)),
            default => number_format((int) $giaTri, 0, ',', '.') . (isset($dn['don_vi']) ? ' ' . $dn['don_vi'] : ''),
        };
    }
}
