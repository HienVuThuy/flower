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
 * ⚠️ TOÀN BỘ DỮ LIỆU Ở ĐÂY LÀ RIÊNG TƯ.
 */
class JournalController extends Controller
{
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

        $ten = $so->title;

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

    public function storeEntry(Request $request, int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:5000'],
            'condition' => ['nullable', Rule::enum(PlantCondition::class)],
            'sticker' => ['nullable', Rule::enum(JournalSticker::class)],
            'photo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],

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

        $so->touch();

        return back()->with('success', 'Đã thêm một trang nhật ký.');
    }

    public function destroyEntry(int $journal, int $entry): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $trang = $so->entries()->findOrFail($entry);

        if ($trang->photo) {
            app(ImageStore::class)->xoa($trang->photo);
        }

        $trang->delete();
        $so->touch();

        return back()->with('success', 'Đã xoá trang nhật ký.');
    }

    public function storeMilestone(Request $request, int $journal): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],

            'due_date' => ['nullable', 'date'],
        ], [], ['title' => 'tên mốc', 'due_date' => 'hạn']);

        $so->milestones()->create([
            'title' => $data['title'],
            'due_date' => $data['due_date'] ?? null,

            'sort_order' => (int) $so->milestones()->max('sort_order') + 1,
        ]);

        $so->touch();

        return back()->with('success', 'Đã thêm mốc.');
    }

    public function toggleMilestone(int $journal, int $milestone): RedirectResponse
    {
        $so = $this->soCuaToi($journal);

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

    private function kiemTraSo(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(JournalKind::class)],
            'description' => ['nullable', 'string', 'max:1000'],

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

    private function ghiChiSo(JournalEntry $entry, array $rows): void
    {
        foreach ($rows as $row) {
            $ten = trim((string) ($row['name'] ?? ''));
            $giaTri = $row['value'] ?? null;

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

    private function cayDaMua(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->whereHas('orderItems.order', fn ($q) => $q->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'main_image']);
    }
}
