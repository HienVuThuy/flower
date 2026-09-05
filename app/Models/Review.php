<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đánh giá sản phẩm.
 *
 * `is_visible` KHÔNG nằm trong $fillable: đó là quyền của cửa hàng, không
 * phải thứ khách gửi lên trong form. Để trong fillable thì thêm một ô ẩn
 * `is_visible=1` vào request là bài bị gỡ tự hiện lại.
 */
class Review extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
    ];

    protected $attributes = [
        'is_visible' => true,
    ];

    /*
     * admin_reply / admin_replied_at KHÔNG nằm trong $fillable.
     *
     * Cùng lý do với `is_visible`: đó là tiếng nói của CỬA HÀNG. Cho vào
     * fillable là mở đường để một request có ô cùng tên tự viết lời
     * "phản hồi từ cửa hàng" dưới đánh giá của chính mình.
     */

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_visible' => 'boolean',
            'admin_replied_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Bài đang hiển thị cho khách xem. */
    /** Cửa hàng đã trả lời đánh giá này chưa. */
    public function hasReply(): bool
    {
        return filled($this->admin_reply);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Tên hiển thị của người viết, đã che bớt.
     *
     * Đánh giá là trang công khai. Hiện đủ họ tên thật của khách hàng lên
     * đó là để lộ thông tin họ chưa đồng ý công bố — "Nguyễn Văn A" thành
     * "Nguyễn V. A".
     */
    public function authorName(): string
    {
        $name = trim((string) $this->user?->name);

        if ($name === '') {
            return 'Khách hàng';
        }

        $parts = preg_split('/\s+/', $name);

        if (count($parts) < 2) {
            return $name;
        }

        $last = array_pop($parts);

        $middle = array_map(
            static fn (string $p) => mb_substr($p, 0, 1) . '.',
            array_slice($parts, 1),
        );

        return implode(' ', array_merge([$parts[0]], $middle, [$last]));
    }

    /**
     * NHỮNG ĐƠN NÀO CHO PHÉP ĐÁNH GIÁ MỘT SẢN PHẨM.
     * ============================================================
     * Đây là quy tắc nghiệp vụ quan trọng nhất của tính năng này, nên nó
     * nằm ở ĐÚNG MỘT chỗ và cả form lẫn controller đều gọi tới đây.
     *
     * Ba điều kiện, thiếu một là không được:
     *   1. Đơn của chính người đang đăng nhập.
     *   2. Đơn đã ở trạng thái "Đã giao" — chưa nhận hàng thì chưa có gì
     *      để nói. Đơn đang giao hay đã huỷ đều không tính.
     *   3. Đơn có chứa sản phẩm đó.
     *
     * Bỏ điều kiện 2 thì mục đánh giá thành nơi ai đặt hàng cũng viết
     * được, kể cả người vừa bấm đặt xong đã vào chấm một sao.
     *
     * @return \Illuminate\Support\Collection<int, Order>
     */
    public static function eligibleOrders(int $userId, int $productId)
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', OrderStatus::Completed)
            ->whereHas('items', fn (Builder $q) => $q->where('product_id', $productId))
            ->orderByDesc('completed_at')
            ->get();
    }

    /**
     * Đơn mà người này còn được viết đánh giá cho sản phẩm này.
     *
     * Đã viết cho đơn nào thì đơn đó không còn trong danh sách — trùng
     * khoá sẽ bị cơ sở dữ liệu chặn, nhưng để khách bấm rồi mới báo lỗi
     * là giao diện tồi.
     */
    public static function pendingOrderFor(int $userId, int $productId): ?Order
    {
        $reviewed = self::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->pluck('order_id')
            ->all();

        return self::eligibleOrders($userId, $productId)
            ->first(fn (Order $order) => ! in_array($order->id, $reviewed, strict: true));
    }
}
