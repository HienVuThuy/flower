<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
/* implements MustVerifyEmail — bật cơ chế xác thực email của Laravel. */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'notify_order_updates' => 'boolean',
            'notify_care_reminders' => 'boolean',
            'locked_at' => 'datetime',
            'visit_streak' => 'integer',
            'last_visit_on' => 'date',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function wantsOrderUpdates(): bool
    {
        return $this->notify_order_updates !== false;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->ordered();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function duoc(\App\Enums\Quyen $quyen): bool
    {
        return in_array($quyen, $this->role->quyen(), true);
    }

    public function laNhanSu(): bool
    {
        return $this->role->laNhanSu();
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ]);

        app(\App\Services\Auth\PasswordResetMailer::class)->send(
            $this,
            $url,
            (int) config('auth.passwords.users.expire', 60),
        );
    }
}
