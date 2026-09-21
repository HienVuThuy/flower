<?php

namespace App\Models;

use App\Enums\BoardingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng nhật ký phiếu chăm hộ: đổi trạng thái, hoặc cửa hàng cập nhật tình trạng cây. */
class BoardingEvent extends Model
{
    public const DOI_TRANG_THAI = 'trang_thai';

    public const CAP_NHAT = 'cap_nhat';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => BoardingStatus::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
