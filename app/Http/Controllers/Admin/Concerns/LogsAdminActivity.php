<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/** Lối tắt để controller quản trị ghi nhật ký thao tác. */
trait LogsAdminActivity
{
    protected function audit(): ActivityLogger
    {
        return app(ActivityLogger::class);
    }

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
