<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Lối tắt để controller quản trị ghi nhật ký thao tác.
 * ============================================================
 * VÌ SAO LÀ TRAIT CHỨ KHÔNG TIÊM QUA CONSTRUCTOR: các controller quản
 * trị hiện có cái có constructor, có cái không. Thêm tham số vào bảy
 * constructor khác nhau là bảy chỗ có thể sai, và ai viết controller
 * thứ tám sẽ không biết phải thêm.
 *
 * Lấy từ container ngay lúc dùng cũng không mất gì: ActivityLogger
 * không giữ trạng thái, và ghi nhật ký không nằm trên đường nóng.
 *
 * TÊN VIỆC theo dạng `đối-tượng.việc` và luôn là tiếng Anh không dấu:
 * nó là MÃ để lọc, không phải câu để đọc. Câu để đọc nằm ở
 * `description`, và câu đó thì viết tiếng Việt.
 */
trait LogsAdminActivity
{
    protected function audit(): ActivityLogger
    {
        return app(ActivityLogger::class);
    }

    /**
     * Ghi một thao tác lên bản ghi, với câu mô tả dựng sẵn.
     *
     * Gộp ba dạng hay dùng nhất — thêm, sửa, xoá — vào một chỗ để câu
     * chữ trong nhật ký đồng nhất. Mỗi controller tự ghép câu thì nhật
     * ký đọc như do bảy người viết, và lọc theo mắt thường thành khó.
     */
    protected function logCrud(string $action, Model $subject, string $loai, string $ten): void
    {
        $viec = match (true) {
            str_ends_with($action, '.created') => 'Thêm',
            str_ends_with($action, '.updated') => 'Sửa',
            str_ends_with($action, '.deleted') => 'Xoá',
            default => 'Thao tác',
        };

        $this->audit()->log(
            $action,
            sprintf('%s %s "%s"', $viec, $loai, $ten),
            $subject,
        );
    }
}
