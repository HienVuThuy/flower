<?php

namespace App\Models;

use App\Enums\AddressLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Một địa chỉ nhận hàng đã lưu của khách.
 *
 * Đây là BẢN SAO ĐỘC LẬP, không phải nguồn dữ liệu của đơn hàng: khi
 * đặt hàng, thông tin được chép sang bảng orders. Khách sửa hoặc xoá
 * địa chỉ sau đó thì đơn cũ vẫn giữ nguyên nơi đã giao — cùng nguyên
 * tắc chụp dữ liệu như order_items.
 */
class Address extends Model
{
    protected $fillable = [
        'user_id',
        'recipient_name',
        'recipient_phone',
        'recipient_email',
        'address_line',
        'ward',
        'district',
        'province',
        'label',
    ];

    /*
     * is_default nằm ngoài $fillable: đặt mặc định là hành vi có ràng
     * buộc (chỉ một địa chỉ được mặc định), phải đi qua makeDefault().
     */

    protected function casts(): array
    {
        return [
            'label' => AddressLabel::class,
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Mặc định lên đầu, sau đó là mới nhất. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderByDesc('id');
    }

    /**
     * Đặt địa chỉ này làm mặc định, bỏ mặc định ở các địa chỉ khác.
     *
     * Trong transaction vì hai lệnh phải cùng thành công: nếu chỉ bỏ
     * được cờ cũ mà chưa kịp bật cờ mới thì khách không còn địa chỉ
     * mặc định nào.
     */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::where('user_id', $this->user_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }

    /** Địa chỉ một dòng để hiển thị. */
    public function fullAddress(): string
    {
        return collect([$this->address_line, $this->ward, $this->district, $this->province])
            ->filter()
            ->implode(', ');
    }

    /**
     * Chuyển sang đúng các khoá mà bước thanh toán đang dùng.
     *
     * Ánh xạ tường minh thay vì đặt trùng tên cột với bảng orders: hai
     * bảng có vòng đời khác nhau, ràng chúng bằng tên cột sẽ khiến đổi
     * một bên là gãy bên kia mà không có cảnh báo.
     *
     * @return array<string, string|null>
     */
    public function toCheckoutData(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_email' => $this->recipient_email,
            'shipping_address' => $this->address_line,
            'shipping_ward' => $this->ward,
            'shipping_district' => $this->district,
            'shipping_province' => $this->province,
        ];
    }
}
