<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected const CACHE_KEY = 'settings.all';

    /**
     * Khoá của bộ nhớ tạm trong container.
     *
     * ĐĂNG KÝ LÀ `scoped` Ở AppServiceProvider, không phải `singleton`.
     */
    public const MEMO_KEY = 'settings.memo';

    /**
     * Bộ nhớ tạm trong phạm vi MỘT REQUEST.
     * ============================================================
     * VÌ SAO CẦN: cache driver mặc định của dự án là `database`, nên mỗi
     * lần gọi `Cache::rememberForever()` vẫn là một truy vấn thật vào
     * bảng `cache`. Layout gọi Setting::get() nhiều lần mỗi trang (tên
     * cửa hàng, theme, hotline, email, tiền tệ...) — không nhớ lại thì
     * đó là chừng ấy truy vấn thừa cho mỗi lượt tải trang.
     *
     * ============================================================
     * VÌ SAO KHÔNG DÙNG `static` — LỖI ĐÃ SỬA.
     *
     * Bản trước giữ mảng này trong một thuộc tính `static`. Với PHP-FPM
     * thì vô hại: mỗi request là một tiến trình mới. Nhưng dự án có
     * những tiến trình SỐNG RẤT LÂU:
     *
     *   - `php artisan schedule:work` chạy liên tục hàng giờ;
     *   - `ghn:dong-bo` gửi thư cho khách qua tiến trình đó, và thư ký
     *     tên bằng StoreProfile::name();
     *   - queue worker (khi có) cũng vậy.
     *
     * `Setting::set()` xoá được cache CHUNG và memo của CHÍNH tiến trình
     * gọi nó — tức là tiến trình web. Tiến trình nền thì không biết gì,
     * và `$memo` của nó chặn trước khi kịp chạm tới cache. Admin đổi tên
     * cửa hàng lúc 9h sáng, và thư gửi đi cả ngày vẫn ký tên cũ cho tới
     * khi có người khởi động lại tiến trình.
     *
     * Lỗi này còn lộ ra ở bài kiểm thử: `RefreshDatabase` khôi phục cơ sở
     * dữ liệu nhưng KHÔNG đụng tới thuộc tính static, nên giá trị của
     * bài trước rò sang bài sau.
     *
     * `scoped` trong container thì được dọn giữa mỗi request và mỗi job —
     * đúng cái phạm vi mà bộ nhớ tạm này cần.
     */
    protected static function all_settings(): array
    {
        return app(self::MEMO_KEY);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_settings()[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        static::quenDem();
    }

    /**
     * Quên cả cache chung lẫn bộ nhớ tạm của tiến trình này.
     *
     * TÁCH RA THÀNH HÀM CÔNG KHAI để bài kiểm thử và những nơi ghi thẳng
     * vào bảng `settings` (seeder, migration) gọi được. Ghi bằng
     * `DB::table('settings')->update()` thì không đi qua set(), và khi ấy
     * trang vẫn đọc giá trị cũ cho tới hết request.
     */
    public static function quenDem(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::MEMO_KEY);
    }

    /**
     * Đọc toàn bộ bảng, qua cache.
     *
     * Gọi từ AppServiceProvider khi dựng binding `scoped`. Để ở đây chứ
     * không viết thẳng trong provider: truy vấn và khoá cache là chuyện
     * của model này.
     *
     * @return array<string, string|null>
     */
    public static function taiTatCa(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all(),
        );
    }
}
