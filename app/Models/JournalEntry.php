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
 * ============================================================
 * ⚠️ DỮ LIỆU RIÊNG TƯ — xem chú thích ở Journal và ở migration.
 *
 * Trang này KHÔNG giữ user_id. Chủ sở hữu suy ra từ quyển sổ, và mọi
 * đường vào đều phải đi qua sổ đã kiểm quyền. Lưu thêm user_id ở đây là
 * tạo ra hai nguồn sự thật cho cùng một câu hỏi, và chúng sẽ lệch nhau
 * đúng vào ngày có ai đó chuyển một trang sang sổ khác.
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
        // Theo thứ tự nhập: người ghi tự quyết chỉ số nào quan trọng
        // trước. Sắp lại theo bảng chữ cái là áp một thứ tự họ không chọn.
        return $this->hasMany(JournalMetric::class)->orderBy('id');
    }

    /**
     * Tiêu đề hiển thị — không bắt buộc nhập, nên phải có phương án lùi.
     *
     * Bắt nhập tiêu đề cho mỗi lần ghi là thêm một rào cản vào việc mà
     * cả tính năng này muốn khuyến khích: ghi thường xuyên, ghi ngắn.
     * Không có tiêu đề thì lấy ngày làm tiêu đề.
     */
    public function displayTitle(): string
    {
        return $this->title
            ?: 'Ghi ngày ' . $this->entry_date->format('d/m/Y');
    }

    /**
     * Một trường riêng của loại sổ, trong cột `data`.
     *
     * ĐỌC QUA ĐÂY, không đọc thẳng $entry->data['price'].
     *
     * Cột `data` là NULL với mọi trang được tạo trước khi có tính năng
     * này, và là mảng thiếu khoá với mọi trang của loại sổ không khai
     * khoá đó. Đọc thẳng thì mỗi chỗ hiển thị phải tự nhớ kiểm hai
     * trường hợp, và chỗ thứ ba sẽ quên.
     */
    public function field(string $key, mixed $macDinh = null): mixed
    {
        return ($this->data ?? [])[$key] ?? $macDinh;
    }

    /**
     * Danh sách việc chăm sóc đã làm trong ngày (sổ sinh trưởng).
     *
     * @return list<JournalSticker>
     */
    public function careActions(): array
    {
        $ket = [];

        foreach ((array) $this->field('care', []) as $khoa) {
            $viec = JournalSticker::tryFrom((string) $khoa);

            // Bỏ qua khoá lạ thay vì vỡ trang: cột JSON không có ràng
            // buộc ở cơ sở dữ liệu, và một lần sửa tay có thể để lại giá
            // trị không còn tồn tại trong enum.
            if ($viec) {
                $ket[] = $viec;
            }
        }

        return $ket;
    }
}
