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

/**
 * `role` được set bằng cách gán thuộc tính trực tiếp
 * (vd: $user->role = UserRole::Customer) chứ không nằm
 * trong danh sách Fillable, để không ai có thể tự phong
 * admin cho mình qua form đăng ký (mass assignment).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
/*
 * implements MustVerifyEmail — bật cơ chế xác thực email của Laravel.
 *
 * Bản thân dòng này KHÔNG chặn ai cả; nó chỉ cho model biết cách trả lời
 * hasVerifiedEmail() và markEmailAsVerified(). Việc chặn nằm ở middleware
 * `verified` khai trong routes/web.php — tách hai chuyện ra để biết chính
 * xác trang nào đang bị khoá, thay vì khoá ngầm cả hệ thống.
 */
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

    /**
     * Tài khoản này có đang bị khoá không.
     *
     * CỐ Ý KHÔNG nằm trong #[Fillable], cùng lý do với `role`: khoá và
     * mở khoá là quyền của quản trị, chỉ đi qua đúng một biểu mẫu
     * (Admin\UserController::updateLock). Cho vào fillable là mở đường
     * để bất cứ biểu mẫu nào có ô trùng tên cũng tự mở khoá được.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Khách này có muốn nhận thư báo đổi trạng thái đơn không.
     *
     * CỐ Ý KHÔNG nằm trong #[Fillable]: đây là tuỳ chọn của riêng chủ tài
     * khoản, chỉ được đổi qua đúng một biểu mẫu (ProfileController::
     * updateNotifications). Cho vào fillable là mở đường để bất cứ biểu
     * mẫu nào có ô cùng tên cũng ghi đè được — kể cả biểu mẫu sửa hồ sơ
     * do người khác gửi lên.
     *
     * Hàm này là NƠI DUY NHẤT trả lời câu hỏi đó, nên chỗ gửi thư không
     * phải tự nhớ quy tắc "null thì coi như bật".
     */
    public function wantsOrderUpdates(): bool
    {
        // Bản ghi cũ tạo trước migration có thể còn null; chưa nói gì thì
        // hiểu là muốn nhận, đúng như giá trị mặc định của cột.
        return $this->notify_order_updates !== false;
    }

    /** Đơn hàng của khách, mới nhất lên trước. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    /** Sổ địa chỉ nhận hàng, địa chỉ mặc định lên đầu. */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->ordered();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Các dòng yêu thích. Sản phẩm lấy qua $user->wishlists->pluck('product'). */
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

    /**
     * Tài khoản này vào được khu vực nào của trang quản trị.
     *
     * ============================================================
     * MỘT CÂU HỎI, MỘT CÂU TRẢ LỜI.
     *
     * Middleware chặn đường dẫn, thanh điều hướng ẩn mục, và trang phân
     * quyền đều hỏi qua đây. Mỗi nơi tự so `role === Admin` một kiểu thì
     * sớm muộn thanh điều hướng ẩn một mục mà đường dẫn vẫn vào được —
     * TRÔNG NHƯ ĐÃ KHOÁ trong khi chưa khoá, thứ nguy hiểm hơn hẳn việc
     * không khoá gì.
     */
    public function duoc(\App\Enums\Quyen $quyen): bool
    {
        return in_array($quyen, $this->role->quyen(), true);
    }

    /** Có vào được trang quản trị không (bất kể khu vực nào). */
    public function laNhanSu(): bool
    {
        return $this->role->laNhanSu();
    }

    /**
     * Gửi thư đặt lại mật khẩu.
     *
     * GHI ĐÈ hành vi mặc định của Laravel. Thư mặc định là tiếng Anh,
     * mang thương hiệu Laravel, và quan trọng hơn: nó gửi thẳng qua
     * Mail::send mà không đi qua lớp kiểm tra "có gửi thật được không"
     * của dự án — nên môi trường đang dùng mailer giả vẫn tưởng đã gửi.
     *
     * $token do Password broker sinh ra; bản LƯU trong cơ sở dữ liệu là
     * mã băm của nó, còn bản gốc chỉ tồn tại trong thư này.
     */
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
