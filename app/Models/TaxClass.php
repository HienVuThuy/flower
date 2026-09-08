<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một nhóm thuế suất mà sản phẩm được gán vào.
 * ============================================================
 * `rate` NULL KHÁC `rate` = 0 — xem migration create_tax_classes_table.
 * Ở đây điều đó thành hai hàm khác nhau:
 *
 *     isExempt()  -> không thuộc diện chịu VAT   (rate NULL)
 *     rate 0      -> chịu thuế suất 0%           (hàng xuất khẩu, ...)
 *
 * Trên hoá đơn hai trường hợp này ghi khác nhau, nên mã nguồn cũng
 * không được phép gộp.
 */
class TaxClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'rate',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            /*
             * decimal:5 khớp với cột và với `orders.tax_rate`.
             *
             * Ép về float ở đây là mở đúng cánh cửa mà TaxCalculator đã
             * đóng lại: mọi phép tính thuế đi qua bcmath dạng chuỗi.
             */
            'rate' => 'decimal:5',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Không thuộc đối tượng chịu VAT (khác với "thuế suất 0%"). */
    public function isExempt(): bool
    {
        return $this->rate === null;
    }

    /** Thuế suất dạng chuỗi bcmath ('0.08000'), hoặc null nếu không chịu thuế. */
    public function rateString(): ?string
    {
        return $this->rate === null ? null : (string) $this->rate;
    }

    /** Nhãn ngắn cho bảng quản trị: "VAT 8%" hoặc "Không chịu VAT". */
    public function rateLabel(): string
    {
        if ($this->isExempt()) {
            return 'Không chịu VAT';
        }

        // rtrim hai lần: '8.00000' -> '8' nhưng '8.50000' -> '8.5'.
        $phanTram = rtrim(rtrim(number_format((float) $this->rate * 100, 3, '.', ''), '0'), '.');

        return $phanTram . '%';
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<TaxClass>  $query */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
