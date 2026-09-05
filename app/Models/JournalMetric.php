<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một chỉ số đo được trong một trang nhật ký.
 * ============================================================
 * ⚠️ DỮ LIỆU RIÊNG TƯ — xem chú thích ở Journal và ở migration.
 *
 * TÊN DO NGƯỜI DÙNG ĐẶT. Đó là cả lý do bảng này tồn tại thay vì mấy
 * cột cố định: người trồng lan ghi "số nụ", người chơi bonsai ghi "đường
 * kính thân", người theo dõi giá ghi "giá tại chợ Bưởi". Đoán trước mọi
 * chỉ số của mọi loài là việc không bao giờ xong.
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

    /**
     * Số kèm đơn vị, bỏ phần thập phân thừa.
     *
     * "24" chứ không "24.00": chiều cao 24cm ghi thành "24.00cm" đọc như
     * một con số máy móc. Nhưng "24.5" thì phải giữ nguyên — cắt hết
     * thập phân là làm mất độ chính xác người ta cố ý ghi.
     */
    public function display(): string
    {
        $so = rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',');

        return $this->unit ? $so . ' ' . $this->unit : $so;
    }
}
