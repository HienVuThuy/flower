<?php

namespace App\Providers;

use App\Enums\InquiryStatus;
use App\Models\BulkOrderInquiry;
use App\Services\Promotion\ActivePromotionProvider;
use App\Services\Search\SearchDictionary;
use App\Services\Theme\ThemeRegistry;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton để trong một request chỉ đọc setting/DB một lần.
        $this->app->singleton(ThemeRegistry::class);
        $this->app->singleton(ActivePromotionProvider::class);

        /*
         * ResponsiveImage PHẢI là singleton, nếu không bộ nhớ tạm bên
         * trong nó vô dụng.
         *
         * Component <x-site.image> gọi app(ResponsiveImage::class) một
         * lần cho MỖI ảnh. Không đăng ký ở đây thì mỗi lần gọi là một
         * đối tượng MỚI với bộ nhớ tạm rỗng, nên nó lại đi hỏi Cache —
         * mà dự án đặt CACHE_STORE=database, tức mỗi lần hỏi là một câu
         * truy vấn thật.
         *
         * Đo được trên /san-pham trước khi sửa: 25 câu
         * `select * from cache` trong một lần mở trang, chiếm 25 trong
         * tổng 36 truy vấn. Phần tối ưu ảnh tự nó tạo ra một N+1.
         */
        $this->app->singleton(\App\Services\Media\ResponsiveImage::class);

        /*
         * BỘ NHỚ TẠM CỦA BẢNG `settings` — `scoped`, KHÔNG `singleton`.
         *
         * `singleton` sống suốt đời tiến trình. Với `php artisan
         * schedule:work` chạy hàng giờ thì nghĩa là: admin đổi tên cửa
         * hàng lúc 9h, và thư gửi đi cả ngày vẫn ký tên cũ.
         *
         * `scoped` được container dọn giữa mỗi request và mỗi job — đúng
         * phạm vi mà bộ nhớ tạm này cần. Xem chú thích ở App\Models\Setting.
         */
        $this->app->scoped(
            \App\Models\Setting::MEMO_KEY,
            fn () => \App\Models\Setting::taiTatCa(),
        );

        /*
         * Từ điển tìm kiếm cũng phải là singleton.
         *
         * Nó được hỏi nhiều lần trong MỘT lần tìm: mỗi từ khoá một lần
         * kiểm tra "có trong catalog không", rồi lại một lần nữa để gợi ý
         * sửa lỗi gõ. Không singleton thì mỗi lớp phụ thuộc nhận một bản
         * riêng, và bộ nhớ tạm bên trong mỗi bản đều lạnh — hoá ra đọc
         * cache lặp đi lặp lại cho cùng một dữ liệu.
         */
        $this->app->singleton(SearchDictionary::class);
    }

    public function boot(): void
    {
        /*
         * PHÂN TRANG DÙNG MARKUP BOOTSTRAP 5.
         *
         * Mặc định Laravel in ra view Tailwind. Dự án này KHÔNG có
         * Tailwind, nên hậu quả là:
         *   - `sm:hidden` không tồn tại -> khối dành cho điện thoại
         *     không bị ẩn, hiện chồng lên khối màn hình lớn;
         *   - icon mũi tên là <svg> ăn class `w-5 h-5` (cũng không tồn
         *     tại) -> mất ràng buộc kích thước và phình to gần hết
         *     màn hình.
         *
         * Bootstrap 5 đã có sẵn trong dự án, và
         * resources/css/components/pagination.css từ trước tới nay vẫn
         * nhắm vào `.pagination .page-link` — tức là CSS viết cho markup
         * Bootstrap nhưng markup đó chưa bao giờ được in ra. Một dòng
         * dưới đây khớp lại hai bên.
         */
        Paginator::useBootstrapFive();

        /*
         * MỘT NƠI DUY NHẤT ĐỊNH NGHĨA "MẬT KHẨU HỢP LỆ".
         *
         * Quy tắc này trước đây viết thẳng trong RegisterRequest. Nay có
         * thêm màn hình đặt lại mật khẩu, nếu chép sang đó thì hai nơi sẽ
         * lệch nhau ngay lần đầu ai đó siết quy tắc — và lệch theo hướng
         * nguy hiểm: đặt lại mật khẩu dễ hơn lúc đăng ký.
         *
         * Password::defaults() là cơ chế sẵn có của Laravel cho đúng việc
         * này; mọi nơi chỉ cần gọi Password::defaults().
         */
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        /*
         * Số yêu cầu đặt số lượng lớn chưa xử lý — hiển thị dạng
         * badge trên sidebar admin. Dùng View Composer thay vì bắt
         * mọi controller admin tự truyền biến này.
         */
        View::composer('layouts.admin', function ($view) {
            $view->with(
                'pendingInquiryCount',
                BulkOrderInquiry::where('status', InquiryStatus::New)->count()
            );
        });

        /*
         * Theme dùng ở cả layout công khai lẫn admin, nên chia sẻ
         * sẵn thay vì để Blade tự gọi Setting::get() rải rác.
         */
        View::composer(['layouts.app', 'layouts.admin', 'welcome'], function ($view) {
            $registry = app(ThemeRegistry::class);

            $view->with([
                'activeTheme' => $registry->activeKey(),
                'activeThemeEffect' => $registry->activeEffect(),
                // Bộ ảnh hero đổi theo theme; chỉ trang chủ dùng tới.
                'heroImages' => $registry->heroImages(),
            ]);
        });
    }
}
