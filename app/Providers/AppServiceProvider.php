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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeRegistry::class);
        $this->app->singleton(ActivePromotionProvider::class);

        $this->app->singleton(\App\Services\Media\ResponsiveImage::class);

        $this->app->scoped(
            \App\Models\Setting::MEMO_KEY,
            fn () => \App\Models\Setting::taiTatCa(),
        );

        $this->app->singleton(SearchDictionary::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }

        $this->gioiHanTanSuat();

        foreach (\App\Enums\Quyen::cases() as $quyen) {
            \Illuminate\Support\Facades\Gate::define(
                $quyen->value,
                fn (\App\Models\User $user) => $user->duoc($quyen),
            );
        }

        Paginator::useBootstrapFive();

        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        View::composer('layouts.admin', function ($view) {
            $view->with(
                'pendingInquiryCount',
                BulkOrderInquiry::where('status', InquiryStatus::New)->count()
            );
        });

        View::composer(['layouts.app', 'layouts.admin', 'welcome'], function ($view) {
            $registry = app(ThemeRegistry::class);

            $view->with([
                'activeTheme' => $registry->activeKey(),
                'activeThemeEffect' => $registry->activeEffect(),
                'heroImages' => $registry->heroImages(),
            ]);
        });
    }

    /**
     * Chặn lạm dụng và làm nghẽn máy chủ (DoS ở tầng ứng dụng).
     * Chống DDoS thể tích thì phải chặn ở tầng mạng / CDN, ứng dụng không làm được.
     */
    private function gioiHanTanSuat(): void
    {
        /* Trần chung cho mọi request web: đủ rộng cho người dùng thật, chặn kịch bản bắn liên tục. */
        RateLimiter::for('chung', fn (Request $request) => Limit::perMinute((int) config('app.tran_moi_phut', 300))->by(
            $request->user()?->id ?: $request->ip(),
        ));

        /* Việc tốn tài nguyên hoặc dễ bị dò: tạo tài khoản, thử mã giảm giá, đặt đơn. */
        RateLimiter::for('nhay-cam', fn (Request $request) => Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip(),
        ));

        /* Trợ lý AI tốn tiền theo lượt hỏi: chặn cả theo phút lẫn theo ngày. */
        RateLimiter::for('tro-ly-ai', fn (Request $request) => [
            Limit::perMinute(\App\Services\Shop\ThamSoKinhDoanh::so('ai.moi_phut'))->by($request->user()?->id ?: $request->ip()),
            Limit::perDay(\App\Services\Shop\ThamSoKinhDoanh::so('ai.moi_ngay'))->by($request->user()?->id ?: $request->ip()),
        ]);
    }
}
