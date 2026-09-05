<?php

namespace App\Http\Controllers\Shop;

use App\Enums\JournalKind;
use App\Enums\PlantCondition;
use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Services\Media\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Nhật ký cá nhân — sổ, trang nhật ký, chỉ số.
 * ============================================================
 * ⚠️ TOÀN BỘ DỮ LIỆU Ở ĐÂY LÀ RIÊNG TƯ.
 *
 * Người ta ghi vào đây chuyện cây nhà mình chết vì quên tưới, giá mình
 * đang chờ, mục tiêu mình chưa làm được. Đó không phải dữ liệu để bán
 * hàng, và cửa hàng không được dùng nó cho gợi ý hay thống kê.
 *
 * ============================================================
 * CÁCH ÉP QUYỀN RIÊNG TƯ: LỌC NGAY LÚC TRUY VẤN.
 *
 * `soCuaToi()` là cửa DUY NHẤT để lấy một quyển sổ. Nó lọc theo
 * `user_id` ngay trong câu truy vấn rồi mới `findOrFail`.
 *
 * Cách hay gặp hơn là `Journal::findOrFail($id)` rồi `abort_unless($j->
 * user_id === Auth::id(), 403)`. Hai vấn đề:
 *
 *   1. Có một khoảnh khắc đối tượng của người khác đã nằm trong tay code
 *      chưa kiểm. Thêm một dòng ở giữa hai câu đó — ghi log, nạp quan
 *      hệ, bắn sự kiện — là rò dữ liệu.
 *   2. 403 XÁC NHẬN quyển sổ đó CÓ TỒN TẠI. Người dò id sẽ đếm được
 *      người khác có bao nhiêu sổ. Lọc trước rồi 404 thì không phân biệt
 *      được "không có" với "không phải của bạn".
 */
class JournalController extends Controller
{
    /**
     * Lấy một quyển sổ CỦA CHÍNH NGƯỜI ĐANG ĐĂNG NHẬP, hoặc 404.
     *
     * Xem chú thích đầu lớp về việc vì sao lọc trước chứ không kiểm sau.
     */
    private function soCuaToi(int $id): Journal
    {
        return Journal::query()
            ->ownedBy((int) Auth::id())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $luuTru = $request->boolean('luu-tru');

        $journals = Journal::query()
            ->ownedBy((int) Auth::id())
            ->where('is_archived', $luuTru)
            ->with('product:id,name,slug,main_image')
            ->withCount('entries')
            ->orderByDesc('updated_at')
            ->get();

        return view('shop.journals.index', [
            'journals' => $journals,
            'dangXemLuuTru' => $luuTru,
            'soLuuTru' => Journal::query()->ownedBy((int) Auth::id())->where('is_archived', true)->count(),
            'kinds' => JournalKind::cases(),
        ]);
    }

    public function create(): View
    {
        return view('shop.journals.form', [
            'journal' => new Journal(['kind' => JournalKind::Growth]),
            'kinds' => JournalKind::cases(),
            'products' => $this->cayDaMua(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->kiemTraSo($request);

        $journal = new Journal($data);
        $journal->user_id = Auth::id();

        /*
         * Chỉ số vẽ biểu đồ mặc định theo loại sổ.
         *
         * Người mới tạo sổ chưa biết mình sẽ ghi chỉ số gì, nên chọn hộ
         * một cái hợp lý. Họ đổi được bất cứ lúc nào ở trang sổ.
         */
        $journal->chart_metric ??= $journal->kind->defaultChartMetric();

        $journal->save();

        return redirect()
            ->route('shop.journals.show', $journal)
            ->with('success', 'Đã tạo sổ "' . $journal->title . '".');
    }

    public function show(Request $request, int $journal): View
    {
        $so = $this->soCuaToi($journal);

        $so->load(['product', 'entries.metrics']);

        /*
         * Chỉ số vẽ biểu đồ: ưu tiên thứ người dùng vừa chọn trên URL,
         * rồi mới tới thứ đã lưu trong sổ.
         *
         * Đổi biểu đồ bằng một đường dẫn chứ không bằng JavaScript: khách
         * gửi link cho nhau được, nút Back chạy đúng, và trang vẫn dùng
         * được khi JavaScript hỏng.
         */
        $tenChiSo = $so->metricNames();
        $chiSo = $request->query('chi-so');

        if (! $chiSo || ! $tenChiSo->contains($chiSo)) {
            $chiSo = $tenChiSo->contains($so->chart_metric) ? $so->chart_metric : $tenChiSo->first();
        }

        return view('shop.journals.show', [
            'journal' => $so,
            'metricNames' => $tenChiSo,
            'chartMetric' => $chiSo,
            'series' => $so->metricSeries($chiSo),
            'conditions' => PlantCondition::cases(),
        ]);
    }

    public function edit(int $journal): View
    {
        return view('shop.journals.form', [
            'journal' => $this->soCuaToi($journal),
            'kinds' => JournalKind::cases(),
            'products' => $this->cayDaMua(),
        ]);
    }

    public function update(Request $request, int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $so->fill($this->kiemTraSo($request))->save();

        return redirect()
            ->route('shop.journals.show', $so)
            ->with('success', 'Đã lưu thay đổi.');
    }

    /** Ẩn khỏi danh sách chính, KHÔNG xoá — xem chú thích ở migration. */
    public function archive(int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $so->is_archived = ! $so->is_archived;
        $so->save();

        return back()->with(
            'success',
            $so->is_archived ? 'Đã đưa sổ vào lưu trữ.' : 'Đã đưa sổ trở lại.',
        );
    }

    public function destroy(int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        /*
         * XOÁ THẬT, không xoá mềm.
         *
         * Đây là dữ liệu riêng tư mà người dùng chủ động yêu cầu xoá.
         * Giữ lại một bản "đã xoá" trong cơ sở dữ liệu là không làm đúng
         * điều họ vừa yêu cầu. Trang nhật ký và chỉ số đi theo nhờ khoá
         * ngoại `cascadeOnDelete`.
         */
        $ten = $so->title;
        $so->delete();

        return redirect()
            ->route('shop.journals.index')
            ->with('success', 'Đã xoá sổ "' . $ten . '" cùng toàn bộ nội dung bên trong.');
    }

    /* ================= TRANG NHẬT KÝ ================= */

    public function storeEntry(Request $request, int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:5000'],
            'condition' => ['nullable', Rule::enum(PlantCondition::class)],
            'photo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],

            /*
             * CHỈ SỐ — tên và giá trị đi theo cặp.
             *
             * `metrics.*.name` nullable vì hàng cuối của biểu mẫu luôn để
             * trống cho người dùng thêm; hàng nào thiếu tên hoặc thiếu
             * giá trị sẽ bị bỏ qua khi ghi, không phải báo lỗi. Bắt lỗi
             * một hàng trống mà người ta không định điền là chặn họ vì
             * một việc họ không làm.
             */
            'metrics' => ['nullable', 'array', 'max:12'],
            'metrics.*.name' => ['nullable', 'string', 'max:60'],
            'metrics.*.value' => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
            'metrics.*.unit' => ['nullable', 'string', 'max:20'],
        ], [], [
            'entry_date' => 'ngày ghi',
            'body' => 'nội dung',
            'photo' => 'ảnh',
        ]);

        DB::transaction(function () use ($so, $data, $request) {
            $entry = $so->entries()->create([
                'entry_date' => $data['entry_date'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'condition' => $data['condition'] ?? null,
                'photo' => $request->hasFile('photo')
                    ? app(ImageStore::class)->luu($request->file('photo'), 'journals')
                    : null,
            ]);

            $this->ghiChiSo($entry, $data['metrics'] ?? []);
        });

        /*
         * Chạm vào `updated_at` của sổ.
         *
         * Danh sách sổ xếp theo lần sửa gần nhất. Không chạm thì một
         * quyển ghi đều đặn hằng ngày vẫn tụt xuống dưới quyển tạo sau
         * mà chưa ghi gì.
         */
        $so->touch();

        return back()->with('success', 'Đã thêm một trang nhật ký.');
    }

    public function destroyEntry(int $journal, int $entry): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        /*
         * Tìm trang TRONG quyển sổ đã kiểm quyền, không tìm theo id trần.
         *
         * `JournalEntry::findOrFail($entry)` sẽ xoá được trang của người
         * khác nếu ai đó đoán đúng id — quyền của quyển sổ không tự lan
         * sang trang.
         */
        $trang = $so->entries()->findOrFail($entry);

        // Dọn cả ảnh và bản WebP đã sinh; không thì thư mục phình ra với
        // ảnh riêng tư của người đã xoá.
        if ($trang->photo) {
            app(ImageStore::class)->xoa($trang->photo);
        }

        $trang->delete();
        $so->touch();

        return back()->with('success', 'Đã xoá trang nhật ký.');
    }

    /* ================= NỘI BỘ ================= */

    /** @return array<string, mixed> */
    private function kiemTraSo(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(JournalKind::class)],
            'description' => ['nullable', 'string', 'max:1000'],

            /*
             * SẢN PHẨM PHẢI LÀ CÂY NGƯỜI NÀY ĐÃ MUA.
             *
             * Không chỉ là "tồn tại trong bảng products": cho gắn sổ vào
             * bất kỳ sản phẩm nào thì trang sổ trở thành một cách dò xem
             * cửa hàng có bán gì — và tệ hơn, một cách dựng dữ liệu giả
             * về việc mình đã mua.
             */
            'product_id' => ['nullable', 'integer', Rule::in($this->cayDaMua()->pluck('id')->all())],

            'started_at' => ['nullable', 'date', 'before_or_equal:today'],
            'chart_metric' => ['nullable', 'string', 'max:60'],

            'target_metric' => ['nullable', 'string', 'max:60'],
            'target_value' => ['nullable', 'numeric'],
            'target_unit' => ['nullable', 'string', 'max:20'],
            'target_date' => ['nullable', 'date'],
        ], [], [
            'title' => 'tên sổ',
            'kind' => 'loại sổ',
        ]);
    }

    /**
     * Ghi các chỉ số của một trang, bỏ qua hàng chưa điền đủ.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function ghiChiSo(JournalEntry $entry, array $rows): void
    {
        foreach ($rows as $row) {
            $ten = trim((string) ($row['name'] ?? ''));
            $giaTri = $row['value'] ?? null;

            // Thiếu một trong hai thì hàng đó không có nghĩa gì cả.
            if ($ten === '' || $giaTri === null || $giaTri === '') {
                continue;
            }

            $entry->metrics()->create([
                'name' => $ten,
                'value' => $giaTri,
                'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
            ]);
        }
    }

    /**
     * Những cây người này ĐÃ MUA — để gắn sổ vào.
     *
     * Lấy từ đơn hàng thật, không lấy từ giỏ hay danh sách yêu thích:
     * "cây của tôi" nghĩa là cây đã về tay, không phải cây đang ngắm.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function cayDaMua(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->whereHas('orderItems.order', fn ($q) => $q->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'main_image']);
    }
}
