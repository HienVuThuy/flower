<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Services\Shop\Money;
use App\Services\Tax\TaxCalculator;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TaxClass;
use App\Services\Shop\Provinces;
use App\Services\Shop\StoreProfile;
use App\Services\Shop\ServiceCommitments;
use App\Services\Theme\HeroImages;
use App\Services\Theme\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $registry = app(ThemeRegistry::class);

        $activeTheme = $registry->activeKey();
        $availableThemes = $registry->all();
        $store = [];

        foreach (array_keys(StoreProfile::FIELDS) as $key) {
            $store[$key] = StoreProfile::get($key);
        }

        $heroImages = [];

        foreach (array_keys($availableThemes) as $key) {
            $heroImages[$key] = $registry->customHeroImages($key);
        }

        return view('admin.settings.edit', [
            'activeTheme' => $activeTheme,
            'availableThemes' => $availableThemes,
            'heroImages' => $heroImages,
            'heroMax' => HeroImages::MAX,
            'heroMaxKb' => HeroImages::MAX_KB,
            'store' => $store,
            'provinces' => Provinces::all(),
            'commitments' => ServiceCommitments::all(),
            'commitmentIcons' => ServiceCommitments::icons(),
            'commitmentMax' => ServiceCommitments::MAX,

            'currency' => [
                'currency_code' => Money::code(),
                'currency_symbol' => Money::symbol(),
                'currency_position' => Money::position(),
                'currency_decimals' => (string) Money::decimals(),
            ],

            'tax' => [
                'enabled' => app(TaxCalculator::class)->enabled(),
                'rate_percent' => app(TaxCalculator::class)->ratePercent(),
            ],

            'taxClasses' => TaxClass::orderBy('id')->get(),

            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $m) => [
                    'label' => $m->label(),
                    'hint' => $m->hint(),
                    'online' => $m->isOnline(),
                    'ready' => $m->isConfigured(),
                    'gateway' => $m->gatewayKey(),
                ])
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(app(ThemeRegistry::class)->keys())],

            'site_name' => ['required', 'string', 'max:60'],
            'site_tagline' => ['nullable', 'string', 'max:80'],

            'site_logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],

            'site_hotline' => ['nullable', 'string', 'max:30', function (string $attr, mixed $value, \Closure $fail) {
                if (filled($value) && ! \App\Services\Shop\StoreProfile::laSoDienThoai(trim((string) $value))) {
                    $fail('Hotline phải là số điện thoại (8-15 chữ số), ví dụ 0912 345 678. Để trống nếu chưa có.');
                }
            }],
            'site_email' => ['nullable', 'email', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:255'],

            'site_province' => ['nullable', Rule::in(Provinces::all())],

            'commitments' => ['nullable', 'array', 'max:' . ServiceCommitments::MAX],
            'commitments.*.icon' => ['nullable', 'string', Rule::in(array_keys(ServiceCommitments::icons()))],
            'commitments.*.title' => ['nullable', 'string', 'max:80'],
            'commitments.*.note' => ['nullable', 'string', 'max:120'],

            'hero' => ['nullable', 'array'],
            'hero.*.keep' => ['nullable', 'array'],
            'hero.*.keep.*.path' => ['nullable', 'string', 'max:255'],
            'hero.*.keep.*.alt' => ['nullable', 'string', 'max:150'],
            'hero.*.remove' => ['nullable', 'array'],

            'hero_files' => ['nullable', 'array'],
            'hero_files.*' => ['nullable', 'array', 'max:' . HeroImages::MAX],
            'hero_files.*.*' => [
                'file',
                'image',
                'mimes:' . implode(',', HeroImages::MIMES),
                'max:' . HeroImages::MAX_KB,
            ],

            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:99.999'],

            'tax_classes' => ['nullable', 'array'],
            'tax_classes.*.rate_percent' => ['nullable', 'numeric', 'min:0', 'max:99.999'],
            'tax_classes.*.is_active' => ['nullable', 'boolean'],
        ], [
            'theme.required' => 'Vui lòng chọn theme.',
            'theme.in' => 'Theme không hợp lệ.',
            'site_email.email' => 'Email không đúng định dạng.',
            'site_province.in' => 'Tỉnh/thành không hợp lệ.',
            'site_name.required' => 'Tên cửa hàng không được để trống.',
            'site_logo.mimes' => 'Logo phải là ảnh PNG, JPG hoặc WebP (không nhận SVG).',
            'site_logo.max' => 'Logo tối đa 512KB.',
            'tax_rate_percent.max' => 'Thuế suất phải nhỏ hơn 100%.',
            'tax_classes.*.rate_percent.max' => 'Thuế suất của nhóm phải nhỏ hơn 100%.',
            'tax_classes.*.rate_percent.numeric' => 'Thuế suất của nhóm phải là số.',
        ]);

        Setting::set('theme', $data['theme']);

        foreach (array_keys(StoreProfile::FIELDS) as $key) {
            if ($key === 'site_logo') {
                continue;
            }

            Setting::set($key, $data[$key] ?? null);
        }

        $this->saveLogo($request, $data);
        $this->saveTaxRate($data);
        $this->saveTaxClasses($data);

        ServiceCommitments::save($data['commitments'] ?? []);

        $this->saveHeroImages($request, $data);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Đã lưu cấu hình. Giao diện cập nhật ngay lập tức, không cần rebuild.');
    }

    private function saveLogo(Request $request, array $data): void
    {
        $anh = app(\App\Services\Media\ImageStore::class);
        $cu = StoreProfile::get('site_logo');

        if ($request->boolean('remove_logo')) {
            $anh->xoa($cu);
            Setting::set('site_logo', null);

            return;
        }

        if (! $request->hasFile('site_logo')) {
            return;
        }

        $moi = $anh->luu($request->file('site_logo'), 'branding');

        $anh->xoa($cu);

        Setting::set('site_logo', $moi);
    }

    private function saveTaxRate(array $data): void
    {
        $phanTram = $data['tax_rate_percent'] ?? null;

        Setting::set(
            TaxCalculator::SETTING_KEY,
            ($phanTram === null || $phanTram === '')
                ? null
                : bcdiv((string) $phanTram, '100', 5),
        );
    }

    private function saveTaxClasses(array $data): void
    {
        foreach ($data['tax_classes'] ?? [] as $id => $dong) {
            $nhom = TaxClass::find((int) $id);

            if (! $nhom) {
                continue;
            }

            $phanTram = $dong['rate_percent'] ?? null;

            $nhom->update([
                'rate' => ($phanTram === null || $phanTram === '')
                    ? null
                    : bcdiv((string) $phanTram, '100', 5),

                'is_active' => (bool) ($dong['is_active'] ?? false),
            ]);
        }
    }

    private function saveHeroImages(Request $request, array $data): void
    {
        $themeKeys = app(ThemeRegistry::class)->keys();

        $submitted = array_unique(array_merge(
            array_keys($data['hero'] ?? []),
            array_keys($request->file('hero_files') ?? []),
        ));

        foreach ($submitted as $themeKey) {
            if (! in_array($themeKey, $themeKeys, true)) {
                continue;
            }

            $block = $data['hero'][$themeKey] ?? [];
            $keep = $block['keep'] ?? [];

            foreach ($block['remove'] ?? [] as $index) {
                unset($keep[$index]);
            }

            HeroImages::saveTheme(
                $themeKey,
                array_values($keep),
                $request->file("hero_files.$themeKey") ?? [],
            );
        }
    }
}
