<?php

namespace App\Models;

use App\Enums\FlowerLotStatus;
use App\Enums\FlowerQuality;
use App\Enums\FlowerUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần lấy hoa về.
 *
 * `status`, `closed_at`, `hao_hut`, `code`, `created_by` CỐ Ý không nằm
 * trong $fillable: đóng lô là một HÀNH ĐỘNG có tác động thật (lô đó
 * thành giá vốn của kỳ), không phải một ô trong biểu mẫu.
 */
class FlowerLot extends Model
{
    protected $fillable = [
        'flower_kind_id',
        'supplier_id',
        'purchased_at',
        'quantity',
        'unit',
        'total_cost',
        'quality',
        'note',
    ];

    protected $attributes = [
        'status' => 'dang_dung',
        'hao_hut' => 0,
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'quantity' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'hao_hut' => 'decimal:2',
            'unit' => FlowerUnit::class,
            'status' => FlowerLotStatus::class,
            'quality' => FlowerQuality::class,
            'closed_at' => 'datetime',
        ];
    }

    public function kind(): BelongsTo
    {
        return $this->belongsTo(FlowerKind::class, 'flower_kind_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDaDong(Builder $q): Builder
    {
        return $q->where('status', FlowerLotStatus::DaDong->value);
    }

    public function scopeDangDung(Builder $q): Builder
    {
        return $q->where('status', FlowerLotStatus::DangDung->value);
    }

    public function daDong(): bool
    {
        return $this->status === FlowerLotStatus::DaDong;
    }

    /**
     * Giá mỗi đơn vị của lô này.
     *
     * Trả null khi số lượng bằng 0 — chia cho 0 là lỗi, và "giá mỗi cành
     * của một lô không có cành nào" không phải một câu có nghĩa.
     */
    public function donGia(): ?string
    {
        if (bccomp((string) $this->quantity, '0', 2) <= 0) {
            return null;
        }

        return bcdiv((string) $this->total_cost, (string) $this->quantity, 2);
    }

    /**
     * Tỉ lệ hao hụt, phần trăm.
     *
     * Đây là thước đo CHẤT LƯỢNG chứ không phải tiền: cùng một giá, vựa
     * hao 5% và vựa hao 20% không phải hai lựa chọn ngang nhau.
     */
    public function tiLeHaoHut(): ?float
    {
        if (bccomp((string) $this->quantity, '0', 2) <= 0) {
            return null;
        }

        return round((float) $this->hao_hut / (float) $this->quantity * 100, 1);
    }

    /** Lô đã mở bao nhiêu ngày — dùng để nhắc lô quên đóng. */
    public function soNgayMo(): int
    {
        $den = $this->closed_at ?? now();

        return (int) $this->purchased_at->startOfDay()->diffInDays($den->copy()->startOfDay());
    }
}
