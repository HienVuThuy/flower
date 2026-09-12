<?php

namespace App\Models;

use App\Enums\SupplierKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một nơi cửa hàng lấy hàng.
 *
 * Xem chú thích dài ở migration create_suppliers_table: vì sao phải là
 * một bảng chứ không phải ô chữ tự do trên phiếu nhập.
 */
class Supplier extends Model
{
    protected $fillable = [
        'name',
        'kind',
        'phone',
        'email',
        'address',
        'note',
        'is_active',
    ];

    protected $attributes = [
        'kind' => 'khac',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'kind' => SupplierKind::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<StockReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    /**
     * Chỉ nhà cung cấp còn làm ăn — dùng cho ô chọn trên biểu mẫu.
     *
     * Người đã ngừng vẫn nằm trong cơ sở dữ liệu và vẫn đọc được ở lịch
     * sử; chỉ là không bày ra để chọn mới nữa.
     */
    public function scopeDangHoatDong(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
