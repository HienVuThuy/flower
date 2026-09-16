<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một chỉ số đo được trong một trang nhật ký.
 * ⚠️ DỮ LIỆU RIÊNG TƯ — xem chú thích ở Journal và ở migration.
 */
class JournalMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function display(): string
    {
        $so = rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',');

        return $this->unit ? $so . ' ' . $this->unit : $so;
    }
}
