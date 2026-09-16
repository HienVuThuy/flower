<?php

namespace App\Models;

use App\Enums\FlowerLotStatus;
use App\Enums\FlowerQuality;
use App\Enums\FlowerUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần lấy hoa về. */
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
            'tra_lai_qty' => 'decimal:2',
            'tra_lai_tien' => 'decimal:2',
            'tra_lai_ly_do' => \App\Enums\ReturnReason::class,
            'tra_lai_settlement' => \App\Enums\ReturnSettlement::class,
            'tra_lai_at' => 'datetime',
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

    public function tienThucTe(): string
    {
        if ($this->tra_lai_tien === null) {
            return bcadd((string) $this->total_cost, '0', 2);
        }

        return bcsub((string) $this->total_cost, (string) $this->tra_lai_tien, 2);
    }

    public function daTraLai(): bool
    {
        return $this->tra_lai_qty !== null;
    }

    public function donGia(): ?string
    {
        if (bccomp((string) $this->quantity, '0', 2) <= 0) {
            return null;
        }

        return bcdiv((string) $this->total_cost, (string) $this->quantity, 2);
    }

    public function tiLeHaoHut(): ?float
    {
        if (bccomp((string) $this->quantity, '0', 2) <= 0) {
            return null;
        }

        return round((float) $this->hao_hut / (float) $this->quantity * 100, 1);
    }

    public function soNgayMo(): int
    {
        $den = $this->closed_at ?? now();

        return (int) $this->purchased_at->startOfDay()->diffInDays($den->copy()->startOfDay());
    }
}
