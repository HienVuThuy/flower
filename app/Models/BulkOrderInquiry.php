<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkOrderInquiry extends Model
{
    /**
     * status/admin_notes/handled_by/handled_at chỉ thực sự được
     * gán qua UpdateInquiryStatusRequest (admin, đã validate) —
     * form public (StoreBulkOrderInquiryRequest) không khai báo
     * các field này nên validated() không bao giờ chứa chúng.
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'company_name',
        'occasion',
        'event_date',
        'event_location',
        'quantity_estimate',
        'budget_min',
        'budget_max',
        'color_preference',
        'flower_preference',
        'preferred_contact',
        'message',
        'status',
        'admin_notes',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'status' => InquiryStatus::class,
        'handled_at' => 'datetime',
        'quantity_estimate' => 'integer',
        // 'date' chứ không phải 'datetime': ngày sự kiện không có giờ,
        // và cast sang datetime sẽ gắn thêm 00:00 rồi in ra "01/09 00:00"
        // — một con số vô nghĩa mà người đọc phải bỏ qua.
        'event_date' => 'date',
        'budget_min' => 'integer',
        'budget_max' => 'integer',
        'preferred_contact' => \App\Enums\ContactChannel::class,
    ];

    /**
     * Khoảng ngân sách viết cho người đọc, hoặc null nếu khách bỏ trống.
     *
     * Xử lý cả ba trường hợp khách điền một nửa: chỉ từ, chỉ đến, hoặc
     * cả hai. Bắt điền đủ cặp là bắt khách biết thứ họ chưa biết.
     */
    public function budgetText(): ?string
    {
        $money = fn (int $v) => number_format($v, 0, ',', '.').'đ';

        return match (true) {
            $this->budget_min && $this->budget_max => $money($this->budget_min).' – '.$money($this->budget_max),
            (bool) $this->budget_min => 'từ '.$money($this->budget_min),
            (bool) $this->budget_max => 'tối đa '.$money($this->budget_max),
            default => null,
        };
    }

    /**
     * Còn bao nhiêu ngày nữa tới sự kiện. Âm nghĩa là đã qua.
     *
     * Dùng để xếp phiếu theo mức gấp — phiếu cho đám cưới tuần sau gấp
     * hơn phiếu cho hội nghị tháng sau, dù phiếu nào gửi trước.
     */
    public function daysUntilEvent(): ?int
    {
        return $this->event_date
            ? (int) now()->startOfDay()->diffInDays($this->event_date->startOfDay(), false)
            : null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
