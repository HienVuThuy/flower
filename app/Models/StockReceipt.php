<?php

namespace App\Models;

use App\Enums\StockReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một phiếu nhập kho.
 *
 * Xem chú thích dài ở migration create_stock_receipts_tables: vì sao
 * không sửa thẳng `products.stock_quantity`.
 */
class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'supplier',
        'note',
        'received_at',
    ];

    /*
     * `status`, `posted_at`, `created_by`, `created_by_name` CỐ Ý không
     * nằm trong $fillable.
     *
     * Cùng lý do với `orders.status`: ghi sổ là một HÀNH ĐỘNG có tác
     * động thật (kho được cộng thêm), không phải một ô trong biểu mẫu.
     * Chỉ StockReceiptService::ghiSo() được phép đặt chúng.
     */

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'status' => StockReceiptStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    /** @return HasMany<StockReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPosted(): bool
    {
        return $this->status === StockReceiptStatus::Posted;
    }

    /** Tổng số đơn vị hàng trên phiếu. */
    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Tổng tiền của phiếu.
     *
     * BỎ QUA dòng chưa điền giá. Coi chúng là 0 thì tổng tiền thấp hơn
     * sự thật mà không có gì báo — xem chú thích `unit_cost` ở migration.
     */
    public function totalCost(): float
    {
        return (float) $this->items
            ->filter(fn (StockReceiptItem $i) => $i->unit_cost !== null)
            ->sum(fn (StockReceiptItem $i) => (float) $i->unit_cost * $i->quantity);
    }

    /** Có dòng nào chưa điền giá không — giao diện phải nói ra. */
    public function hasUnpricedItems(): bool
    {
        return $this->items->contains(fn (StockReceiptItem $i) => $i->unit_cost === null);
    }

    public function actorLabel(): string
    {
        return $this->createdBy?->name ?? $this->created_by_name ?? 'Không rõ';
    }
}
