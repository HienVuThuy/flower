<?php

namespace App\Models;

use App\Enums\JournalKind;
use App\Enums\JournalTheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Một quyển sổ nhật ký của một người dùng.
 * ============================================================
 * ⚠️ DỮ LIỆU RIÊNG TƯ. Xem chú thích ở migration
 * `create_journals_tables`: không nơi nào ngoài chính chủ được đọc.
 *
 * `scopeOwnedBy()` là cửa duy nhất nên dùng để lấy sổ. Gọi
 * `Journal::find($id)` trần rồi mới kiểm quyền là để lộ một khoảnh khắc
 * mà đối tượng đã nằm trong tay code chưa kiểm — và khoảnh khắc đó là
 * chỗ lỗi hay chui vào.
 */
class Journal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'kind',
        'description',
        'product_id',

        /*
         * `cover_image` CỐ Ý KHÔNG nằm ở đây — cùng lý do với `user_id`
         * và với `JournalMilestone::done_at`.
         *
         * Nó là ĐƯỜNG DẪN TỆP do `ImageStore` sinh ra, không phải giá trị
         * nhận từ biểu mẫu. Để nó trong $fillable thì `fill($validated)`
         * gán thẳng đối tượng UploadedFile đè lên đường dẫn cũ, và tệp cũ
         * không còn ai biết đường mà xoá — mỗi lần đổi bìa là một tệp rác
         * nằm lại vĩnh viễn trong ổ đĩa.
         *
         * Lỗi này đã xảy ra thật và bị bài `JournalCoverTest::
         * thay_anh_bia_thi_xoa_tep_cu` bắt được.
         */
        'theme_key',
        'started_at',
        'chart_metric',
        'target_metric',
        'target_value',
        'target_unit',
        'target_date',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'kind' => JournalKind::class,
            'theme_key' => JournalTheme::class,
            'started_at' => 'date',
            'target_date' => 'date',
            'target_value' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Cây được ghi nhật ký, nếu là cây mua ở cửa hàng. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Các mốc cần đạt — chỉ có nghĩa với sổ Mục tiêu.
     *
     * Sắp theo `sort_order` chứ không theo trạng thái xong/chưa: người
     * dùng tự xếp thứ tự các bước, và đẩy mốc đã xong xuống cuối là làm
     * mất chính cái thứ tự đó. Mốc xong vẫn nằm nguyên chỗ, chỉ khác cách
     * hiển thị.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(JournalMilestone::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class)
            // Mới nhất lên trước: người mở sổ ra thường muốn xem lần ghi
            // gần đây, không phải trang đầu tiên của hai năm trước.
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
    }

    /**
     * CHỈ SỔ CỦA NGƯỜI NÀY.
     *
     * Mọi truy vấn nhật ký phải đi qua đây. Đó là lý do nó là một scope
     * chứ không phải một dòng `where` chép đi chép lại ở controller —
     * chép tay thì chỗ thứ năm sẽ quên, và chỗ đó là một lỗ rò dữ liệu
     * riêng tư.
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /**
     * Tên mọi chỉ số đã từng ghi trong sổ này.
     *
     * Dùng để dựng ô chọn "vẽ biểu đồ theo chỉ số nào" — chỉ liệt kê
     * những chỉ số THẬT SỰ CÓ SỐ LIỆU. Bày ra một chỉ số chưa ghi lần nào
     * thì chọn vào sẽ được một biểu đồ trống.
     *
     * @return Collection<int, string>
     */
    public function metricNames(): Collection
    {
        return JournalMetric::query()
            ->whereIn('journal_entry_id', $this->entries()->select('id'))
            ->distinct()
            ->orderBy('name')
            ->pluck('name');
    }

    /**
     * Số liệu của một chỉ số theo thời gian, cũ trước mới sau.
     *
     * XẾP TĂNG DẦN THEO NGÀY, ngược với `entries()`. Biểu đồ đọc từ trái
     * sang phải theo dòng thời gian; đưa dữ liệu xếp giảm dần vào thì
     * đường biểu diễn chạy ngược và mọi kết luận đọc ra đều sai chiều.
     *
     * @return Collection<int, array{date: \Illuminate\Support\Carbon, value: float, unit: ?string}>
     */
    public function metricSeries(?string $name): Collection
    {
        if (! $name) {
            return collect();
        }

        return JournalMetric::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_metrics.journal_entry_id')
            ->where('journal_entries.journal_id', $this->id)
            ->where('journal_metrics.name', $name)
            ->orderBy('journal_entries.entry_date')
            ->get([
                'journal_entries.entry_date',
                'journal_metrics.value',
                'journal_metrics.unit',
            ])
            ->map(fn ($row) => [
                'date' => \Illuminate\Support\Carbon::parse($row->entry_date),
                'value' => (float) $row->value,
                'unit' => $row->unit,
            ]);
    }

    /**
     * Tiến độ tới mục tiêu, tính bằng phần trăm — hoặc null.
     *
     * NULL KHI CHƯA ĐỦ DỮ KIỆN, và đó là câu trả lời đúng chứ không phải
     * 0%. Sổ chưa đặt mục tiêu, hoặc đặt rồi mà chưa ghi số nào, thì "0%"
     * đọc ra là "đã bắt đầu và chưa đi được bước nào" — sai hẳn với
     * "chưa có gì để đo".
     */
    public function goalProgress(): ?float
    {
        if (! $this->target_metric || $this->target_value === null) {
            return null;
        }

        $moiNhat = $this->metricSeries($this->target_metric)->last();

        if (! $moiNhat) {
            return null;
        }

        $dich = (float) $this->target_value;

        // Mục tiêu bằng 0 thì phép chia không có nghĩa. Coi như đạt khi
        // số đo cũng về 0 — ví dụ mục tiêu "0 lá vàng".
        if ($dich == 0.0) {
            return $moiNhat['value'] == 0.0 ? 100.0 : 0.0;
        }

        return round($moiNhat['value'] / $dich * 100, 1);
    }

    /* ================= RIÊNG TỪNG LOẠI SỔ ================= */

    /** Bộ giao diện của sổ; chưa chọn thì lấy mặc định theo loại sổ. */
    public function theme(): JournalTheme
    {
        return $this->theme_key ?? $this->kind->defaultTheme();
    }

    /**
     * Tỉ lệ mốc đã hoàn thành — hoặc null khi CHƯA ĐẶT MỐC NÀO.
     *
     * null, không phải 0%. Cùng lý do với `goalProgress()`: "0%" đọc ra
     * là "đã đặt mốc và chưa làm được mốc nào", trong khi sự thật là chưa
     * có mốc nào để làm. Hai chuyện đó dẫn tới hai hành động khác nhau.
     *
     * @return array{done: int, total: int, percent: float}|null
     */
    public function milestoneProgress(): ?array
    {
        $tong = $this->milestones->count();

        if ($tong === 0) {
            return null;
        }

        $xong = $this->milestones->filter(fn (JournalMilestone $m) => $m->isDone())->count();

        return [
            'done' => $xong,
            'total' => $tong,
            'percent' => round($xong / $tong * 100, 1),
        ];
    }

    /**
     * Thống kê giá cho sổ Theo dõi giá — hoặc null khi chưa đủ dữ liệu.
     * ============================================================
     * CẦN ÍT NHẤT HAI LẦN KHẢO GIÁ.
     *
     * Với đúng một lần khảo thì "thấp nhất", "cao nhất" và "trung bình"
     * đều là chính con số đó — ba ô hiện cùng một số, trông như một bảng
     * thống kê mà không thống kê gì cả. Cả tính năng này tồn tại để trả
     * lời "giá đang lên hay xuống", và một điểm thì không trả lời được.
     *
     * @return array{low: float, high: float, avg: float, latest: float,
     *               count: int, place_low: ?string, spread: float}|null
     */
    public function priceStats(): ?array
    {
        $gia = $this->entries
            ->map(fn (JournalEntry $e) => [
                'value' => $e->field('price'),
                'place' => $e->field('place'),
                'date' => $e->entry_date,
            ])
            ->filter(fn (array $r) => is_numeric($r['value']))
            ->map(fn (array $r) => ['value' => (float) $r['value']] + $r);

        if ($gia->count() < 2) {
            return null;
        }

        $thap = $gia->sortBy('value')->first();
        $cao = (float) $gia->max('value');

        // Lần khảo GẦN NHẤT theo ngày ghi, không phải bản ghi cuối trong
        // danh sách: người dùng ghi bù ngày cũ là chuyện bình thường
        // (QĐ-126), và khi đó bản ghi cuối lại là giá của tháng trước.
        $moiNhat = (float) $gia->sortBy(fn (array $r) => $r['date']->timestamp)->last()['value'];

        return [
            'low' => (float) $thap['value'],
            'high' => $cao,
            'avg' => round($gia->avg('value'), 2),
            'latest' => $moiNhat,
            'count' => $gia->count(),
            'place_low' => $thap['place'] ?: null,
            'spread' => $cao - (float) $thap['value'],
        ];
    }

    /**
     * Điểm đánh giá trung bình cho sổ Phân tích — null khi chưa chấm.
     *
     * @return array{avg: float, count: int}|null
     */
    public function ratingSummary(): ?array
    {
        $diem = $this->entries
            ->map(fn (JournalEntry $e) => $e->field('rating'))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        if ($diem->isEmpty()) {
            return null;
        }

        return ['avg' => round($diem->avg(), 1), 'count' => $diem->count()];
    }

    /**
     * Đếm số lần đã làm từng việc chăm sóc (sổ sinh trưởng).
     *
     * Trả lời câu người trồng cây thật sự hỏi khi mở sổ ra: *tháng này
     * mình đã tưới mấy lần rồi?* — thứ mà đọc dòng thời gian thì phải
     * đếm bằng tay.
     *
     * @return Collection<string, int> nhãn việc => số lần
     */
    public function careTally(): Collection
    {
        $dem = [];

        foreach ($this->entries as $entry) {
            foreach ($entry->careActions() as $viec) {
                $dem[$viec->value] = ($dem[$viec->value] ?? 0) + 1;
            }
        }

        return collect($dem)->sortDesc();
    }

    /**
     * Những trang CÓ ẢNH, cũ trước mới sau — dải ảnh theo thời gian.
     *
     * XẾP TĂNG DẦN, ngược với `entries()`. Dải ảnh để nhìn ra cây lớn
     * thế nào, mà lớn lên thì đọc từ trái sang phải theo thời gian. Đưa
     * thứ tự giảm dần vào thì cây trông như đang teo lại.
     *
     * @return Collection<int, JournalEntry>
     */
    public function photoStrip(int $limit = 12): Collection
    {
        return $this->entries
            ->filter(fn (JournalEntry $e) => (bool) $e->photo)
            ->sortBy(fn (JournalEntry $e) => $e->entry_date->timestamp)
            ->take($limit)
            ->values();
    }
}
