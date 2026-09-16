<?php

namespace App\Models;

use App\Services\Time\Gio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một kỳ của kế hoạch trả góp (kỳ 0 là khoản trả trước). */
class InstallmentPayment extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'amount' => 'decimal:2',
            'due_on' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest('id');
    }

    public function nhan(): string
    {
        return $this->sequence === 0 ? 'Trả trước' : 'Kỳ ' . $this->sequence;
    }

    public function daTra(): bool
    {
        return $this->paid_at !== null;
    }

    public function traTre(): bool
    {
        return $this->paid_at !== null
            && Gio::hien($this->paid_at)->toDateString() > $this->due_on->toDateString();
    }

    public function quaHan(): bool
    {
        return $this->paid_at === null
            && now(Gio::mui())->toDateString() > $this->due_on->toDateString();
    }
}
