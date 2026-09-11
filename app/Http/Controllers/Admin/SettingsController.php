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
        /*
         * Giá trị mặc định nằm trong StoreProfile, không gõ lại ở đây.
         * Hai nơi cùng giữ một mặc định là hai nơi sẽ lệch nhau.
         */
        $store = [];

        foreach (array_keys(StoreProfile::FIELDS) as $key) {
            $store[$key] = StoreProfile::get($key);
        }

        /*
         * Ảnh hero của TỪNG theme, không chỉ theme đang bật: admin phải
         * chuẩn bị được ảnh cho theme Tết từ trước Tết, chứ không phải
         * bật theme lên rồi mới được thay ảnh.
         */
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

            /*
             * TIỀN TỆ — bốn tham số, đọc từ Money để mặc định chỉ có một
             * nơi giữ.
             */
            'currency' => [
                'currency_code' => Money::code(),
                'currency_symbol' => Money::symbol(),
                'currency_position' => Money::position(),
                'currency_decimals' => (string) Money::decimals(),
            ],

            /*
             * THUẾ — chỉ đưa ra thuế suất. Việc BẬT/TẮT nằm ở
             * config/tax.php vì đó là quyết định "cửa hàng này có ghi
             * nhận thuế hay không", không phải một con số nghiệp vụ đổi
             * theo tháng.
             */
            'tax' => [
                'enabled' => app(TaxCalculator::class)->enabled(),
                'rate_percent' => app(TaxCalculator::class)->ratePercent(),
            ],

            /*
             * NHÓM THUẾ SUẤT — kể cả nhóm đang tắt.
             *
             * Khác trang sửa sản phẩm (chỉ mời chọn nhóm còn bật): đây là
             * chỗ DUY NHẤT bật lại được một nhóm đã tắt. Lọc mất nó ở đây
             * thì tắt nhầm một nhóm là mất hẳn, không có đường quay lại.
             */
            'taxClasses' => TaxClass::orderBy('id')->get(),

            /*
             * HÌNH THỨC THANH TOÁN — hiện trạng thái, KHÔNG cho bật tắt
             * bằng công tắc.
             *
             * Một cổng chỉ dùng được khi có đủ khoá bí mật trong .env;
             * cho admin bật một cổng chưa cấu hình là dựng ra lựa chọn
             * hỏng giữa đường, sau khi khách đã điền hết địa chỉ. Bảng
             * này trả lời câu "vì sao MoMo chưa hiện ra" — thứ mà trước
             * đây không chỗ nào trong giao diện trả lời được.
             */
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

            /*
             * TÊN CỬA HÀNG BẮT BUỘC.
             *
             * Nó xuất hiện ở tiêu đề mọi trang, chân trang và sáu mẫu
             * thư. Để trống thì khách nhận một lá thư ký tên bằng khoảng
             * trắng — nên đây là trường duy nhất trong nhóm này không
             * cho nullable.
             */
            'site_name' => ['required', 'string', 'max:60'],
            'site_tagline' => ['nullable', 'string', 'max:80'],

            /*
             * LOGO — kiểm bằng NỘI DUNG tệp, không tin phần mở rộng.
             *
             * `mimes` đọc magic number, nên đổi tên shell.php thành
             * shell.png không lọt được (Guide §10 — tải lên an toàn).
             *
             * Cho phép SVG? KHÔNG. SVG là XML và chạy được JavaScript
             * bên trong; một tệp logo trở thành một lỗ XSS trên mọi
             * trang của cửa hàng.
             */
            'site_logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],

            /*
             * Hotline phải là một SỐ gọi được. Ô chữ tự do từng nhận "demo",
             * và chữ đó đi thẳng vào mọi email gửi khách. Cùng luật với
             * StoreProfile::laSoDienThoai() — một nơi định nghĩa.
             */
            'site_hotline' => ['nullable', 'string', 'max:30', function (string $attr, mixed $value, \Closure $fail) {
                if (filled($value) && ! \App\Services\Shop\StoreProfile::laSoDienThoai(trim((string) $value))) {
                    $fail('Hotline phải là số điện thoại (8-15 chữ số), ví dụ 0912 345 678. Để trống nếu chưa có.');
                }
            }],
            'site_email' => ['nullable', 'email', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:255'],

            /*
             * Tỉnh phải CHỌN TỪ DANH SÁCH, không cho gõ tay.
             *
             * Phí giao tra theo đúng chuỗi tên tỉnh (ShippingRates::
             * zoneOf). Gõ "Hà Nội" thay vì "Thành phố Hà Nội" là rơi vào
             * vùng mặc định và mọi đơn nội thành bị tính giá tỉnh xa.
             */
            'site_province' => ['nullable', Rule::in(Provinces::all())],

            /*
             * Cam kết dịch vụ: mỗi dòng gồm biểu tượng, tiêu đề, ghi chú.
             * Dòng để trống tiêu đề sẽ bị ServiceCommitments::save() loại,
             * nên ở đây tiêu đề chỉ cần nullable.
             */
            'commitments' => ['nullable', 'array', 'max:' . ServiceCommitments::MAX],
            'commitments.*.icon' => ['nullable', 'string', Rule::in(array_keys(ServiceCommitments::icons()))],
            'commitments.*.title' => ['nullable', 'string', 'max:80'],
            'commitments.*.note' => ['nullable', 'string', 'max:120'],

            /*
             * ẢNH HERO.
             *
             * `mimes` kiểm tra bằng nội dung tệp chứ không tin phần mở
             * rộng, nên đổi tên shell.php thành shell.jpg không lọt được
             * (Guide §10 — tải lên an toàn).
             */
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

            /* ---------- TIỀN TỆ ---------- */
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'currency_position' => ['required', Rule::in(['before', 'after'])],
            'currency_decimals' => ['required', 'integer', 'min:0', 'max:4'],

            /* ---------- THUẾ ---------- */
            /*
             * NHẬP THEO PHẦN TRĂM, LƯU THEO THẬP PHÂN.
             *
             * Kế toán nói "8%", không nói "0,08". Bắt admin tự quy đổi là
             * mời một lỗi gõ nhầm gấp 100 lần vào đúng con số thuế —
             * nhập 8 thay vì 0.08 thì mọi đơn ghi 800% thuế.
             */
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:99.999'],

            /*
             * MỨC CỦA TỪNG NHÓM THUẾ.
             *
             * Ô TRỐNG Ở ĐÂY NGHĨA KHÁC ô `tax_rate_percent` bên trên:
             *
             *     tax_rate_percent  trống = "dùng mức mặc định trong config"
             *     nhóm thuế         trống = "KHÔNG thuộc diện chịu VAT"
             *
             * Hai nghĩa khác nhau trên cùng một trang là một cái bẫy thật,
             * nên giao diện phải nói rõ — xem chú thích ở edit.blade.php.
             */
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
            'currency_code.regex' => 'Mã tiền tệ gồm đúng 3 chữ cái in hoa, ví dụ VND.',
            'tax_rate_percent.max' => 'Thuế suất phải nhỏ hơn 100%.',
            'tax_classes.*.rate_percent.max' => 'Thuế suất của nhóm phải nhỏ hơn 100%.',
            'tax_classes.*.rate_percent.numeric' => 'Thuế suất của nhóm phải là số.',
        ]);

        Setting::set('theme', $data['theme']);

        /*
         * Duyệt theo danh sách khoá trong StoreProfile: thêm một trường
         * mới ở đó là nó tự được lưu, không phải nhớ sửa thêm chỗ này.
         *
         * TRỪ `site_logo`: đó là một TỆP, không phải một ô chữ. Để nó
         * lọt vào vòng này thì mỗi lần lưu cấu hình mà không tải logo
         * mới, `$data['site_logo']` vắng mặt và logo bị xoá sạch — một
         * lỗi im lặng, chỉ phát hiện khi mở trang chủ ra xem.
         */
        foreach (array_keys(StoreProfile::FIELDS) as $key) {
            if ($key === 'site_logo') {
                continue;
            }

            Setting::set($key, $data[$key] ?? null);
        }

        $this->saveLogo($request, $data);
        $this->saveCurrency($data);
        $this->saveTaxRate($data);
        $this->saveTaxClasses($data);

        ServiceCommitments::save($data['commitments'] ?? []);

        $this->saveHeroImages($request, $data);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Đã lưu cấu hình. Giao diện cập nhật ngay lập tức, không cần rebuild.');
    }

    /**
     * Lưu bộ ảnh hero cho từng theme.
     *
     * CHỈ đụng tới theme nào thực sự có mặt trong form. Nếu quét toàn bộ
     * theme thì một lần lưu form (ví dụ chỉ đổi số hotline) sẽ ghi đè
     * danh sách rỗng lên mọi theme và xoá sạch ảnh admin đã tải.
     *
     * @param  array<string, mixed>  $data  dữ liệu ĐÃ validate
     */
    /**
     * Lưu hoặc gỡ logo cửa hàng.
     *
     * BA TRẠNG THÁI, không phải hai: tải logo mới / gỡ logo đang có /
     * không đụng gì. Trạng thái thứ ba là mặc định, và nó phải là mặc
     * định — mỗi lần admin sửa số điện thoại rồi bấm Lưu mà logo biến
     * mất thì không ai dám bấm Lưu nữa.
     */
    private function saveLogo(Request $request, array $data): void
    {
        $anh = app(\App\Services\Media\ImageStore::class);
        $cu = StoreProfile::get('site_logo');

        if ($request->boolean('remove_logo')) {
            // Dọn cả bản WebP đã sinh, không chỉ ảnh gốc.
            $anh->xoa($cu);
            Setting::set('site_logo', null);

            return;
        }

        if (! $request->hasFile('site_logo')) {
            return;
        }

        $moi = $anh->luu($request->file('site_logo'), 'branding');

        // Xoá ảnh cũ SAU khi ảnh mới đã lưu xong: hỏng giữa chừng thì
        // cửa hàng còn logo cũ, không phải không còn gì.
        $anh->xoa($cu);

        Setting::set('site_logo', $moi);
    }

    /** Bốn tham số tiền tệ — mặc định nằm ở Money::FIELDS. */
    private function saveCurrency(array $data): void
    {
        foreach (array_keys(Money::FIELDS) as $key) {
            if (array_key_exists($key, $data)) {
                Setting::set($key, (string) $data[$key]);
            }
        }
    }

    /**
     * Thuế suất: NHẬN phần trăm, LƯU thập phân.
     *
     * Quy đổi ở đúng một chỗ này. Để mỗi nơi tự nhân chia 100 là mời một
     * lỗi gấp 100 lần vào con số thuế, và nó sẽ nằm im cho tới kỳ quyết
     * toán.
     *
     * Ô để trống = "dùng mức mặc định trong config", không phải "thuế
     * bằng 0". Muốn 0% thì gõ 0.
     */
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

    /**
     * Mức và trạng thái của từng nhóm thuế.
     *
     * ============================================================
     * CHỈ ĐỘNG VÀO NHỮNG NHÓM CÓ TRONG DỮ LIỆU GỬI LÊN.
     *
     * Duyệt toàn bộ bảng rồi lấy giá trị từ `$data` sẽ biến mọi nhóm
     * vắng mặt thành "không chịu VAT, đang tắt" — và một biểu mẫu gửi
     * thiếu (mạng chập, người dùng bấm nút khác) sẽ xoá sạch cấu hình
     * thuế của cửa hàng mà không có lỗi nào.
     *
     * TRA THEO ID TỪ CƠ SỞ DỮ LIỆU, không tin id trên biểu mẫu: một id
     * bịa ra chỉ đơn giản không tìm thấy và bị bỏ qua.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveTaxClasses(array $data): void
    {
        foreach ($data['tax_classes'] ?? [] as $id => $dong) {
            $nhom = TaxClass::find((int) $id);

            if (! $nhom) {
                continue;
            }

            $phanTram = $dong['rate_percent'] ?? null;

            $nhom->update([
                /*
                 * TRỐNG = NULL = "không thuộc diện chịu VAT".
                 *
                 * KHÁC hẳn 0 ("chịu thuế suất 0%"): hàng 0% vẫn là hàng
                 * chịu thuế và vẫn lên hoá đơn với dòng thuế suất 0%.
                 * Gộp hai thứ này là làm mất một phân biệt nghiệp vụ mà
                 * hoá đơn bắt buộc phải thể hiện.
                 */
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
            // Khoá lạ (form bị sửa tay) thì bỏ qua, không tạo theme mới.
            if (! in_array($themeKey, $themeKeys, true)) {
                continue;
            }

            $block = $data['hero'][$themeKey] ?? [];
            $keep = $block['keep'] ?? [];

            /*
             * Ô "Xoá ảnh này" gửi lên CHỈ SỐ của dòng, không phải đường
             * dẫn — chỉ số thì không giả mạo để trỏ ra tệp khác được.
             */
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
