<?php

namespace App\Http\Controllers\Shop;

use App\Enums\JournalKind;
use App\Enums\JournalSticker;
use App\Enums\JournalTheme;
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
        $journal->cover_image = $this->anhBia($request, null);
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

        $so->load(['product', 'entries.metrics', 'milestones']);

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

        $so->fill($this->kiemTraSo($request));
        $so->cover_image = $this->anhBia($request, $so->cover_image);
        $so->save();

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

        // Ảnh bìa và ảnh từng trang là tệp trên ổ đĩa, khoá ngoại không
        // với tới được. Xoá sổ mà để lại ảnh riêng tư của người đã yêu
        // cầu xoá là không làm đúng điều họ vừa yêu cầu.
        foreach ($so->entries()->whereNotNull("photo")->pluck("photo") as $anh) {
            app(ImageStore::class)->xoa($anh);
        }

        if ($so->cover_image) {
            app(ImageStore::class)->xoa($so->cover_image);
        }

        $so->delete();

        return redirect()
            ->route('shop.journals.index')
            ->with('success', 'Đã xoá sổ "' . $ten . '" cùng toàn bộ nội dung bên trong.');
    }

    /* ================= TRANG NHẬT KÝ ================= */

    public function storeEntry(Request $request, int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        /*
         * LUẬT KIỂM DỰNG THEO LOẠI SỔ.
         *
         * Phần chung ở đây; phần riêng (giá, nơi khảo, chấm điểm, việc đã
         * chăm) lấy từ `JournalKind::dataFields()`. Đó là bộ khoá ĐÓNG —
         * điều kiện để cột JSON `data` không thành thùng rác, đúng ràng
         * buộc đã đặt cho `product_traits`.
         */
        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:5000'],
            'condition' => ['nullable', Rule::enum(PlantCondition::class)],
            'sticker' => ['nullable', Rule::enum(JournalSticker::class)],
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
        ] + $so->kind->dataFields(), [], [
            'entry_date' => 'ngày ghi',
            'body' => 'nội dung',
            'photo' => 'ảnh',
            'price' => 'giá',
            'place' => 'nơi khảo giá',
            'rating' => 'điểm đánh giá',
            'good' => 'phần được',
            'bad' => 'phần chưa được',
        ]);

        DB::transaction(function () use ($so, $data, $request) {
            $entry = $so->entries()->create([
                'entry_date' => $data['entry_date'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'condition' => $data['condition'] ?? null,
                'sticker' => $data['sticker'] ?? null,
                'data' => $this->truongRieng($so, $data),
                'photo' => $request->hasFile('photo')
                    ? app(ImageStore::class)->luu($request->file('photo'), 'journals')
                    : null,
            ]);

            $this->ghiChiSo($entry, $this->hangChiSo($so, $data));
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

    /* ================= MỐC MỤC TIÊU ================= */

    public function storeMilestone(Request $request, int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],

            /*
             * HẠN CỦA MỐC ĐƯỢC PHÉP Ở TƯƠNG LAI — khác hẳn ngày ghi nhật ký.
             *
             * Một trang nhật ký ghi lại thứ ĐÃ quan sát được, nên ngày ở
             * tương lai là dữ liệu chưa tồn tại (QĐ-126). Một cái hạn thì
             * ngược lại: nó gần như luôn ở tương lai, đó là ý nghĩa của nó.
             */
            'due_date' => ['nullable', 'date'],
        ], [], ['title' => 'tên mốc', 'due_date' => 'hạn']);

        $so->milestones()->create([
            'title' => $data['title'],
            'due_date' => $data['due_date'] ?? null,

            // Mốc mới xuống cuối danh sách: người dùng thêm theo thứ tự
            // họ nghĩ ra, và đó thường đã là thứ tự đúng.
            'sort_order' => (int) $so->milestones()->max('sort_order') + 1,
        ]);

        $so->touch();

        return back()->with('success', 'Đã thêm mốc.');
    }

    public function toggleMilestone(int $journal, int $milestone): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        // Tìm mốc TRONG quyển sổ đã kiểm quyền, không tìm theo id trần —
        // cùng lý do như `destroyEntry()`.
        $moc = $so->milestones()->findOrFail($milestone);

        $moc->done_at = $moc->done_at ? null : now();
        $moc->save();

        $so->touch();

        return back();
    }

    public function destroyMilestone(int $journal, int $milestone): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $so->milestones()->findOrFail($milestone)->delete();
        $so->touch();

        return back()->with('success', 'Đã xoá mốc.');
    }

    /* ================= NỘI BỘ ================= */

    /**
     * Lọc ra đúng những trường riêng mà loại sổ này khai báo.
     * ============================================================
     * ĐÂY LÀ CHỐT CHẶN CỦA CỘT JSON `data`.
     *
     * Không ghi thẳng `$request->all()` hay cả `$data` vào cột: làm vậy
     * thì bất kỳ trường nào gửi lên cũng nằm lại trong cơ sở dữ liệu, và
     * ba tháng sau không ai biết trong cột đó có những gì.
     *
     * Chỉ những khoá `JournalKind::dataFields()` khai — đã qua validate —
     * mới được ghi. Khoá không điền thì bỏ hẳn thay vì ghi null: một
     * mảng gọn thì đọc log dễ hơn, và `field()` đã xử lý khoá thiếu.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function truongRieng(Journal $so, array $data): ?array
    {
        $ket = [];

        foreach (array_keys($so->kind->dataFields()) as $khoa) {
            $giaTri = $data[$khoa] ?? null;

            if ($giaTri === null || $giaTri === '' || $giaTri === []) {
                continue;
            }

            $ket[$khoa] = $giaTri;
        }

        return $ket ?: null;
    }

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

            'theme_key' => ['nullable', Rule::enum(JournalTheme::class)],
            'cover_image' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
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
     * Ảnh bìa mới, ảnh cũ giữ nguyên, hay bỏ hẳn.
     * ============================================================
     * BA TRẠNG THÁI, KHÔNG PHẢI HAI.
     *
     * Ô tải tệp để trống có thể nghĩa là "không đổi gì" HOẶC "bỏ ảnh đi"
     * — trình duyệt gửi lên y hệt nhau. Không phân biệt được hai ý đó thì
     * người dùng không bao giờ gỡ được ảnh bìa đã lỡ chọn: mỗi lần lưu là
     * ảnh cũ lại quay về.
     *
     * Nên có ô tích `remove_cover` riêng. Ba nhánh, mỗi nhánh một ý rõ
     * ràng.
     *
     * DỌN TỆP CŨ ở cả hai nhánh thay ảnh và bỏ ảnh — không thì thư mục
     * phình ra với ảnh riêng tư của những quyển sổ đã đổi bìa từ lâu.
     */
    private function anhBia(Request $request, ?string $hienTai): ?string
    {
        if ($request->hasFile('cover_image')) {
            if ($hienTai) {
                app(ImageStore::class)->xoa($hienTai);
            }

            return app(ImageStore::class)->luu($request->file('cover_image'), 'journals');
        }

        if ($request->boolean('remove_cover') && $hienTai) {
            app(ImageStore::class)->xoa($hienTai);

            return null;
        }

        return $hienTai;
    }

    /**
     * Các hàng chỉ số sẽ được ghi — kể cả hàng SUY RA từ ô riêng.
     * ============================================================
     * LỖI ĐÃ SỬA: sổ Theo dõi giá có khối biểu đồ trong `panels()`, nhưng
     * biểu mẫu của nó KHÔNG có hàng chỉ số nào — nó có một ô nhập giá
     * riêng. Nên với một sổ giá do người dùng tự tạo, biểu đồ sẽ trống
     * vĩnh viễn: khối vẽ ra, không bao giờ có dữ liệu, và không có gì
     * giải thích vì sao.
     *
     * Chỉ lộ ra khi tạo sổ giá bằng giao diện thật; dữ liệu mẫu tôi dựng
     * bằng script đã tự ghi thêm chỉ số "Giá" nên nó che mất lỗi.
     *
     * Sửa: giá người dùng vừa nhập ĐƯỢC GHI LUÔN thành chỉ số "Giá". Đây
     * không phải bịa dữ liệu — nó chính là con số họ vừa gõ, chỉ được ghi
     * thêm vào chỗ mà biểu đồ đọc.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function hangChiSo(Journal $so, array $data): array
    {
        $hang = $data['metrics'] ?? [];

        if ($so->kind->hasField('price') && isset($data['price']) && $data['price'] !== '') {
            $hang[] = [
                'name' => 'Giá',
                'value' => $data['price'],
                'unit' => \App\Services\Shop\Money::symbol(),
            ];
        }

        return $hang;
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
