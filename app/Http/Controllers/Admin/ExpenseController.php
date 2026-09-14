<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\Analytics\CashFlowReport;
use App\Services\Time\Gio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sổ thu chi: chi phí vận hành, và lãi ròng ước tính theo tháng.
 * ============================================================
 * LÀM TRONG WEB, KHÔNG PHẢI Ở BẢNG TÍNH RIÊNG — vì phần "thu" đã nằm sẵn ở
 * đây. Ghi chi phí ở Excel thì mỗi tháng phải chép tay doanh thu, tiền nhập
 * hàng, tiền hoàn từ web sang, và hai nơi lệch nhau ngay tháng đầu tiên.
 *
 * Quyền `tai-chinh`: lương từng người và lãi ròng là thứ nhạy cảm nhất của
 * cửa hàng.
 *
 * CÓ NÚT XOÁ, khác nhà cung cấp: không có chứng từ nào trỏ tới một khoản
 * chi, và một khoản ghi nhầm mà không xoá được thì làm sai lãi ròng mãi.
 * Mọi lần thêm, sửa, xoá đều vào nhật ký — kèm số tiền.
 */
class ExpenseController extends Controller
{
    use LogsAdminActivity;

    public const PHUONG_THUC = [
        'tien_mat' => 'Tiền mặt',
        'chuyen_khoan' => 'Chuyển khoản',
        'the' => 'Thẻ',
    ];

    public function index(Request $request, CashFlowReport $bao): View
    {
        $thang = $this->thang($request->query('thang'));
        $khoang = CashFlowReport::khoangThang($thang);

        $q = Expense::query()->orderByDesc('spent_on')->orderByDesc('id');
        $khoang->apDungNgay($q, 'spent_on');

        $dauThang = Carbon::createFromFormat('!Y-m', $thang, Gio::mui());

        return view('admin.expenses.index', [
            'thang' => $thang,
            'nhanThang' => $dauThang->format('m/Y'),
            'thangTruoc' => $dauThang->copy()->subMonth()->format('Y-m'),
            'thangSau' => $thang < $this->thangHienTai() ? $dauThang->copy()->addMonth()->format('Y-m') : null,
            'bao' => $bao->thang($thang),
            'cacKhoan' => $q->get(),
            'soCoDinhChuaChep' => $this->coDinhChuaChep($thang)->count(),
            'homNay' => now(Gio::mui())->toDateString(),
        ]);
    }

    public function create(Request $request): View
    {
        $thang = $this->thang($request->query('thang'));

        // Tháng đang xem là tháng hiện tại thì điền hôm nay; tháng cũ thì điền ngày cuối tháng đó.
        $ngay = $thang === $this->thangHienTai()
            ? now(Gio::mui())->toDateString()
            : Carbon::createFromFormat('!Y-m', $thang, Gio::mui())->endOfMonth()->toDateString();

        return view('admin.expenses.form', [
            'khoan' => new Expense(['spent_on' => $ngay, 'category' => ExpenseCategory::Khac]),
        ]);
    }

    public function edit(Expense $expense): View
    {
        return view('admin.expenses.form', ['khoan' => $expense]);
    }

    public function store(Request $request): RedirectResponse
    {
        $khoan = new Expense($this->duLieu($request));
        $khoan->forceFill([
            'created_by' => Auth::id(),
            'created_by_name' => Auth::user()?->name,
        ])->save();

        $this->audit()->log('expense.created', 'Ghi chi phí: ' . $khoan->description, $khoan, [
            'so_tien' => (string) $khoan->amount,
            'loai' => $khoan->category->value,
            'ngay' => $khoan->spent_on->toDateString(),
        ]);

        return redirect()
            ->route('admin.expenses.index', ['thang' => $khoan->spent_on->format('Y-m')])
            ->with('success', 'Đã ghi khoản chi.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $truoc = (string) $expense->amount;

        $expense->update($this->duLieu($request));

        $this->audit()->log('expense.updated', 'Sửa chi phí: ' . $expense->description, $expense, [
            'so_tien_truoc' => $truoc,
            'so_tien_sau' => (string) $expense->amount,
            'ngay' => $expense->spent_on->toDateString(),
        ]);

        return redirect()
            ->route('admin.expenses.index', ['thang' => $expense->spent_on->format('Y-m')])
            ->with('success', 'Đã lưu khoản chi.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $thang = $expense->spent_on->format('Y-m');

        // Ghi nhật ký TRƯỚC khi xoá: xoá rồi thì không còn gì để kể.
        $this->audit()->log('expense.deleted', 'Xoá chi phí: ' . $expense->description, $expense, [
            'so_tien' => (string) $expense->amount,
            'loai' => $expense->category->value,
            'ngay' => $expense->spent_on->toDateString(),
        ]);

        $expense->delete();

        return redirect()
            ->route('admin.expenses.index', ['thang' => $thang])
            ->with('success', 'Đã xoá khoản chi.');
    }

    /**
     * Chép các khoản CỐ ĐỊNH của tháng trước sang tháng đang xem.
     * ============================================================
     * Người bấm, không phải bộ lập lịch: lương tháng này có thể khác tháng
     * trước. Chép xong thì từng dòng vẫn sửa được trước khi tin nó.
     *
     * CHẠY LẠI KHÔNG NHÂN ĐÔI: một khoản cố định đã có trong tháng này (cùng
     * loại, cùng mô tả) thì bỏ qua. Bấm hai lần — hoặc hai người cùng bấm —
     * mà ra hai dòng lương là trừ lương hai lần khỏi lãi ròng.
     */
    public function copyFixed(Request $request): RedirectResponse
    {
        $thang = $this->thang($request->input('thang'));
        $dauThang = Carbon::createFromFormat('!Y-m', $thang, Gio::mui());

        $soDong = DB::transaction(function () use ($thang, $dauThang) {
            $chep = 0;

            foreach ($this->coDinhChuaChep($thang, khoa: true) as $cu) {
                // Cùng ngày trong tháng; tháng ngắn hơn thì lấy ngày cuối (31/01 → 28/02).
                $ngay = $dauThang->copy()->day(min($cu->spent_on->day, $dauThang->daysInMonth));

                $moi = $cu->replicate(['created_by', 'created_by_name', 'created_at', 'updated_at']);
                $moi->spent_on = $ngay->toDateString();
                $moi->forceFill(['created_by' => Auth::id(), 'created_by_name' => Auth::user()?->name])->save();

                $chep++;
            }

            return $chep;
        });

        if ($soDong > 0) {
            $this->audit()->log('expense.copied', 'Chép ' . $soDong . ' khoản chi cố định sang tháng ' . $dauThang->format('m/Y'), null, [
                'thang' => $thang,
                'so_dong' => $soDong,
            ]);
        }

        return redirect()
            ->route('admin.expenses.index', ['thang' => $thang])
            ->with('success', $soDong > 0
                ? 'Đã chép ' . $soDong . ' khoản cố định. Kiểm lại số tiền tháng này trước khi tin con số lãi.'
                : 'Không có khoản cố định nào cần chép.');
    }

    /**
     * Khoản cố định của tháng TRƯỚC chưa có bản tương ứng trong tháng này.
     *
     * @return \Illuminate\Support\Collection<int, Expense>
     */
    private function coDinhChuaChep(string $thang, bool $khoa = false): \Illuminate\Support\Collection
    {
        $dauThang = Carbon::createFromFormat('!Y-m', $thang, Gio::mui());

        $truoc = CashFlowReport::khoangThang($dauThang->copy()->subMonth()->format('Y-m'));
        $nay = CashFlowReport::khoangThang($thang);

        $q = Expense::query()->where('is_fixed', true)->orderBy('spent_on')->orderBy('id');
        $truoc->apDungNgay($q, 'spent_on');

        $daCo = Expense::query()->where('is_fixed', true);
        $nay->apDungNgay($daCo, 'spent_on');

        if ($khoa) {
            $daCo->lockForUpdate();
        }

        $daCo = $daCo->get(['category', 'description'])
            ->map(fn (Expense $e) => $e->category->value . '|' . mb_strtolower(trim($e->description)))
            ->flip();

        return $q->get()->reject(
            fn (Expense $e) => $daCo->has($e->category->value . '|' . mb_strtolower(trim($e->description)))
        )->values();
    }

    /** @return array<string, mixed> */
    private function duLieu(Request $request): array
    {
        $data = $request->validate([
            /*
             * KHÔNG QUÁ CUỐI THÁNG HIỆN TẠI. Lương trả ngày 25 thì ghi trước
             * được trong tháng; ghi cho tháng sau là một kế hoạch, không phải
             * một khoản đã chi — và nó sẽ nằm sẵn trong lãi ròng tháng sau
             * trước khi tháng đó bắt đầu.
             */
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . now(Gio::mui())->endOfMonth()->toDateString()],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payment_method' => ['nullable', Rule::in(array_keys(self::PHUONG_THUC))],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'spent_on.before_or_equal' => 'Chỉ ghi khoản chi tới cuối tháng này — tháng sau chưa bắt đầu.',
            'amount.gt' => 'Số tiền phải lớn hơn 0.',
        ], [
            'spent_on' => 'ngày chi',
            'category' => 'loại chi phí',
            'description' => 'nội dung',
            'amount' => 'số tiền',
            'payment_method' => 'cách trả',
            'note' => 'ghi chú',
        ]);

        $data['description'] = trim($data['description']);

        // Tiền đồng: không có số lẻ. Làm tròn ở đây một lần, không để mỗi báo cáo tự làm tròn một kiểu.
        $data['amount'] = bcadd(number_format((float) $data['amount'], 0, '.', ''), '0', 2);

        // Ô đánh dấu bỏ tích thì không gửi gì — phải đặt lại, không thì không tắt được.
        $data['is_fixed'] = $request->boolean('is_fixed');

        return $data;
    }

    private function thangHienTai(): string
    {
        return now(Gio::mui())->format('Y-m');
    }

    /** Tháng hợp lệ từ tham số; sai dạng hoặc ở tương lai thì về tháng hiện tại. */
    private function thang(mixed $gt): string
    {
        if (! is_string($gt) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $gt)) {
            return $this->thangHienTai();
        }

        return $gt > $this->thangHienTai() ? $this->thangHienTai() : $gt;
    }
}
