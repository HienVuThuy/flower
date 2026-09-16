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
}
