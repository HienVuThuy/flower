<?php

namespace App\Models;

use App\Enums\JournalSticker;
use App\Enums\PlantCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một trang trong quyển sổ nhật ký.
 * ⚠️ DỮ LIỆU RIÊNG TƯ
 */
class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date',
        'title',
        'body',
        'condition',
        'photo',
        'sticker',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'condition' => PlantCondition::class,
            'sticker' => JournalSticker::class,
            'data' => 'array',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(JournalMetric::class)->orderBy('id');
    }

    public function displayTitle(): string
    {
        return $this->title
            ?: 'Ghi ngày ' . $this->entry_date->format('d/m/Y');
    }

    public function field(string $key, mixed $macDinh = null): mixed
    {
        return ($this->data ?? [])[$key] ?? $macDinh;
    }

    public function careActions(): array
    {
        $ket = [];

        foreach ((array) $this->field('care', []) as $khoa) {
            $viec = JournalSticker::tryFrom((string) $khoa);

            if ($viec) {
                $ket[] = $viec;
            }
        }

        return $ket;
    }
}
