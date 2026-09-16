<?php

namespace App\Models;

use App\Enums\AddressLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** Một địa chỉ nhận hàng đã lưu của khách. */
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

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderByDesc('id');
    }

    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::where('user_id', $this->user_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }

    public function fullAddress(): string
    {
        return collect([$this->address_line, $this->ward, $this->district, $this->province])
            ->filter()
            ->implode(', ');
    }

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
