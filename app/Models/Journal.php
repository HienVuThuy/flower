<?php

namespace App\Models;

use App\Enums\JournalKind;
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
        'cover_image',
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
}
