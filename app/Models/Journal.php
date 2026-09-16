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
 * ⚠️ DỮ LIỆU RIÊNG TƯ.
 */
class Journal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'kind',
        'description',
        'product_id',

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(JournalMilestone::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class)
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function metricNames(): Collection
    {
        return JournalMetric::query()
            ->whereIn('journal_entry_id', $this->entries()->select('id'))
            ->distinct()
            ->orderBy('name')
            ->pluck('name');
    }

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

        if ($dich == 0.0) {
            return $moiNhat['value'] == 0.0 ? 100.0 : 0.0;
        }

        return round($moiNhat['value'] / $dich * 100, 1);
    }

    public function theme(): JournalTheme
    {
        return $this->theme_key ?? $this->kind->defaultTheme();
    }

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

    public function photoStrip(int $limit = 12): Collection
    {
        return $this->entries
            ->filter(fn (JournalEntry $e) => (bool) $e->photo)
            ->sortBy(fn (JournalEntry $e) => $e->entry_date->timestamp)
            ->take($limit)
            ->values();
    }
}
