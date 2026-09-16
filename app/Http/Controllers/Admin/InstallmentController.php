<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InstallmentStatus;
use App\Http\Controllers\Controller;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Services\Audit\ActivityLogger;
use App\Services\Installment\InstallmentException;
use App\Services\Installment\InstallmentService;
use App\Services\Installment\InstallmentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Trang "Trả góp": các kế hoạch, cấu hình trả góp, ghi kỳ trả tại cửa hàng. */
class InstallmentController extends Controller
{
    private const O = [
        'bat' => 'tra_gop.bat',
        'don_toi_thieu' => 'tra_gop.don_toi_thieu',
        'so_ngay_moi_ky' => 'tra_gop.so_ngay_moi_ky',
        'ngay_an_han' => 'tra_gop.ngay_an_han',
        'diem_toi_thieu' => 'tra_gop.diem_toi_thieu',
        'diem_tot' => 'tra_gop.diem_tot',
        'ky_toi_da_thuong' => 'tra_gop.ky_toi_da_thuong',
        'ky_toi_da_tot' => 'tra_gop.ky_toi_da_tot',
        'tra_truoc_thuong' => 'tra_gop.tra_truoc_thuong',
        'tra_truoc_tot' => 'tra_gop.tra_truoc_tot',
    ];

    public function index(Request $request): View
    {
        $loc = InstallmentStatus::tryFrom((string) $request->query('trang_thai', ''));

        $keHoach = InstallmentPlan::query()
            ->with(['order:id,order_number,recipient_name,status', 'user:id,name', 'payments'])
            ->when($loc, fn ($q) => $q->where('status', $loc->value))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.installments.index', [
            'keHoach' => $keHoach,
            'loc' => $loc,
            'dem' => InstallmentPlan::query()->selectRaw('status, COUNT(*) as so')->groupBy('status')->pluck('so', 'status'),
            'cauHinh' => collect(self::O)->mapWithKeys(fn ($khoa, $o) => [$o => InstallmentSettings::get($khoa)])->all(),
        ]);
    }

    public function updateSettings(Request $request, ActivityLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'bat' => ['nullable', 'boolean'],
            'don_toi_thieu' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'so_ngay_moi_ky' => ['required', 'integer', 'min:1', 'max:90'],
            'ngay_an_han' => ['required', 'integer', 'min:0', 'max:30'],
            'diem_toi_thieu' => ['required', 'integer', 'min:0', 'max:100'],
            'diem_tot' => ['required', 'integer', 'max:100', 'gte:diem_toi_thieu'],
            'ky_toi_da_thuong' => ['required', 'integer', 'min:1', 'max:12'],
            'ky_toi_da_tot' => ['required', 'integer', 'max:12', 'gte:ky_toi_da_thuong'],
            'tra_truoc_thuong' => ['required', 'integer', 'min:10', 'max:90'],
            'tra_truoc_tot' => ['required', 'integer', 'min:10', 'lte:tra_truoc_thuong'],
        ], [
            'diem_tot.gte' => 'Điểm của mức tốt phải từ điểm tối thiểu trở lên.',
            'ky_toi_da_tot.gte' => 'Mức tốt phải được ít nhất bằng số kỳ của mức thường.',
            'tra_truoc_tot.lte' => 'Mức tốt phải trả trước không nhiều hơn mức thường.',
        ], [
            'don_toi_thieu' => 'đơn tối thiểu',
            'so_ngay_moi_ky' => 'số ngày mỗi kỳ',
            'ngay_an_han' => 'số ngày ân hạn',
            'diem_toi_thieu' => 'điểm tối thiểu',
            'diem_tot' => 'điểm mức tốt',
            'ky_toi_da_thuong' => 'số kỳ tối đa (mức thường)',
            'ky_toi_da_tot' => 'số kỳ tối đa (mức tốt)',
            'tra_truoc_thuong' => '% trả trước (mức thường)',
            'tra_truoc_tot' => '% trả trước (mức tốt)',
        ]);

        $data['bat'] = $request->boolean('bat') ? '1' : '0';

        InstallmentSettings::luu(collect(self::O)->mapWithKeys(fn ($khoa, $o) => [$khoa => $data[$o]])->all());

        $audit->log('tra-gop.cau-hinh', 'Sửa cấu hình trả góp', null, $data);

        return redirect()->route('admin.installments.index')
            ->with('success', 'Đã lưu cấu hình trả góp. Kế hoạch đang chạy giữ nguyên điều kiện lúc tạo.');
    }

    public function record(Request $request, Order $order, InstallmentService $traGop): RedirectResponse
    {
        $data = $request->validate(['ky_id' => ['required', 'integer', Rule::exists('installment_payments', 'id')]]);

        try {
            $ketQua = $traGop->thuTaiCuaHang($order, (int) $data['ky_id']);
        } catch (InstallmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ketQua === 'completed'
            ? 'Đã ghi kỳ cuối. Khách đã trả đủ — đơn chuyển "Đã thanh toán" và được xác nhận.'
            : 'Đã ghi nhận kỳ trả góp thu tại cửa hàng.');
    }
}
