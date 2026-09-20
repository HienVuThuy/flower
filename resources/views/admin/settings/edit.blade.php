@extends('layouts.admin')

@section('title', 'Cài đặt')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Cài đặt hệ thống</h1>
    <p class="admin-page-subtitle">Đổi theme và thông tin liên hệ — áp dụng ngay, không cần deploy lại.</p>
</div>

<x-admin.nhom-tab ten="cai-dat" />

<div class="row g-4">

    <div class="col-lg-8">

        <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">

            @csrf
            @method('PUT')

            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-1">Giao diện (theme)</h2>

                <p class="text-muted small mb-3">
                    Mỗi theme gồm 5 lớp: màu sắc, artwork trang trí, hiệu ứng
                    chuyển động, ảnh nền không khí và bộ ảnh hero riêng (khung
                    ảnh lớn ở đầu trang chủ tự đổi ảnh). Bấm để xem trước ngay
                    trên trang này trước khi lưu.
                </p>

                <div class="row g-3" id="themePicker">

                    @foreach($availableThemes as $themeKey => $theme)

                        <div class="col-sm-6">

                            <label
                                class="theme-option-card {{ $activeTheme === $themeKey ? 'is-selected' : '' }}"
                                data-theme-option="{{ $themeKey }}"
                            >

                                <input
                                    type="radio"
                                    name="theme"
                                    value="{{ $themeKey }}"
                                    class="d-none"
                                    data-theme-effect="{{ $theme['effect'] ?? '' }}"
                                    @checked($activeTheme === $themeKey)
                                >

                                <span
                                    class="theme-option-card__swatch"
                                    style="background: linear-gradient(135deg, {{ $theme['swatch'][0] }}, {{ $theme['swatch'][1] }});"
                                ></span>

                                <span class="fw-semibold">{{ $theme['label'] }}</span>

                                <span class="text-caption d-block mt-1">
                                    {{ $theme['description'] }}
                                </span>

                                @if(empty($theme['effect']))
                                    <span class="text-caption d-block mt-2">Không có hiệu ứng chuyển động</span>
                                @endif

                            </label>

                        </div>

                    @endforeach

                </div>

                <x-form-error name="theme"/>

            </div>

            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-1">Ảnh khung lớn trang chủ</h2>

                <p class="text-muted small mb-3">
                    Khung ảnh lớn ở đầu trang chủ tự đổi ảnh sau mỗi 6 giây.
                    Mỗi theme có bộ ảnh riêng — chọn ảnh cho theme nào thì mở
                    mục của theme đó. Chưa tải ảnh nào thì website dùng bộ ảnh
                    mặc định đi kèm theme.
                    Tối đa {{ $heroMax }} ảnh mỗi theme, mỗi ảnh dưới
                    {{ round($heroMaxKb / 1024) }} MB.
                </p>

                @foreach($availableThemes as $themeKey => $theme)
                    @php $themeHero = $heroImages[$themeKey] ?? []; @endphp

                    <details class="hero-manager" @if($activeTheme === $themeKey) open @endif>

                        <summary class="hero-manager__summary">
                            <span class="fw-semibold text-nowrap">{{ $theme['label'] }}</span>
                            <span class="text-caption">
                                @if($themeHero)
                                    {{ count($themeHero) }} ảnh riêng
                                @else
                                    đang dùng bộ mặc định
                                @endif
                            </span>
                        </summary>

                        <div class="hero-manager__body">

                            @if($themeHero)
                                <div class="hero-manager__grid">
                                    @foreach($themeHero as $i => $img)
                                        <div class="hero-thumb">

                                            <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}">

                                            <input type="hidden"
                                                   name="hero[{{ $themeKey }}][keep][{{ $i }}][path]"
                                                   value="{{ $img['path'] }}">

                                            <input type="text"
                                                   name="hero[{{ $themeKey }}][keep][{{ $i }}][alt]"
                                                   value="{{ $img['alt'] }}"
                                                   class="form-control form-control-sm mt-2"
                                                   maxlength="150"
                                                   placeholder="Mô tả ảnh (cho người khiếm thị)">

                                            <label class="hero-thumb__remove">
                                                <input type="checkbox"
                                                       name="hero[{{ $themeKey }}][remove][]"
                                                       value="{{ $i }}">
                                                Xoá ảnh này
                                            </label>

                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-caption mb-3">
                                    Chưa có ảnh riêng. Trang chủ đang dùng
                                    {{ count($theme['hero'] ?? []) }} ảnh mặc định của theme này.
                                </p>
                            @endif

                            <label class="form-label small mb-1" for="hero-file-{{ $themeKey }}">
                                Thêm ảnh cho {{ $theme['label'] }}
                            </label>

                            <input type="file"
                                   id="hero-file-{{ $themeKey }}"
                                   name="hero_files[{{ $themeKey }}][]"
                                   class="form-control form-control-sm @error('hero_files.' . $themeKey . '.*') is-invalid @enderror"
                                   accept="image/jpeg,image/png,image/webp"
                                   multiple>

                            <div class="form-text">
                                Chọn được nhiều ảnh một lúc. Ảnh ngang, tối thiểu khoảng 1200px chiều rộng.
                            </div>

                            @error('hero_files.' . $themeKey . '.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                        </div>

                    </details>
                @endforeach

            </div>

            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-3">Nhận diện cửa hàng</h2>

                <p class="text-muted small mb-3">
                    Hiện ở logo đầu trang, chân trang, tiêu đề mọi trang và trong thư gửi khách.
                </p>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Tên cửa hàng <span class="text-danger">*</span></label>
                        <input type="text" name="site_name"
                               class="form-control @error('site_name') is-invalid @enderror"
                               value="{{ old('site_name', $store['site_name']) }}"
                               maxlength="60" required>
                        <x-form-error name="site_name"/>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Dòng phụ dưới tên</label>
                        <input type="text" name="site_tagline"
                               class="form-control @error('site_tagline') is-invalid @enderror"
                               value="{{ old('site_tagline', $store['site_tagline']) }}"
                               maxlength="80" placeholder="Hoa tươi &amp; Cây cảnh">
                        <x-form-error name="site_tagline"/>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Logo</label>

                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="d-inline-flex align-items-center justify-content-center"
                                  style="width:56px;height:56px;border:1px solid var(--border-soft);border-radius:8px;">
                                <x-site.brand :size="40" :show-text="false" />
                            </span>

                            @if($store['site_logo'])
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="remove_logo" value="1" id="removeLogo">
                                    <label class="form-check-label" for="removeLogo">
                                        Gỡ logo, quay lại hình mặc định
                                    </label>
                                </div>
                            @endif
                        </div>

                        <input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp"
                               class="form-control @error('site_logo') is-invalid @enderror">
                        <x-form-error name="site_logo"/>
                        <p class="form-text">
                            PNG, JPG hoặc WebP, tối đa 512KB. Nền trong suốt hiển thị đẹp nhất.
                            <strong>Không nhận SVG</strong> vì lý do an toàn.
                        </p>
                    </div>

                </div>

            </div>

            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-3">Thông tin liên hệ</h2>

                <p class="text-muted small mb-3">
                    Hiện ở chân trang, trang Liên hệ và trong thư gửi khách.
                </p>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Hotline</label>
                        <input type="text" name="site_hotline"
                               class="form-control @error('site_hotline') is-invalid @enderror"
                               value="{{ old('site_hotline', $store['site_hotline']) }}">
                        <x-form-error name="site_hotline"/>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email liên hệ</label>
                        <input type="email" name="site_email"
                               class="form-control @error('site_email') is-invalid @enderror"
                               value="{{ old('site_email', $store['site_email']) }}">
                        <x-form-error name="site_email"/>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Địa chỉ cửa hàng</label>
                        <input type="text" name="site_address"
                               class="form-control @error('site_address') is-invalid @enderror"
                               value="{{ old('site_address', $store['site_address']) }}">
                        <x-form-error name="site_address"/>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tỉnh/thành đặt cửa hàng</label>
                        <select name="site_province" class="form-select @error('site_province') is-invalid @enderror">
                            @foreach($provinces as $province)
                                <option value="{{ $province }}"
                                    @selected(old('site_province', $store['site_province']) === $province)>
                                    {{ $province }}
                                </option>
                            @endforeach
                        </select>
                        <x-form-error name="site_province"/>
                        <p class="form-text">
                            Dùng làm điểm xuất phát khi tính phí giao hàng.
                            Đổi tỉnh thì phải soát lại bảng vùng trong
                            <code>config/shipping.php</code>.
                        </p>
                    </div>

                </div>

            </div>

            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-3">Đơn vị tiền tệ</h2>

                <p class="text-muted small mb-3">
                    Áp dụng cho mọi số tiền: thẻ sản phẩm, giỏ hàng, đơn hàng,
                    thư gửi khách và trang quản trị.
                    Đang hiển thị: <strong>{{ \App\Services\Shop\Money::format(260000) }}</strong>
                </p>

                <p class="mb-0">
                    <strong>Đồng Việt Nam (VND)</strong> — cố định. Giá sản phẩm, MoMo và GHN đều tính bằng đồng;
                    đổi ký hiệu sang đơn vị khác mà không quy đổi sẽ in sai mọi số tiền.
                </p>

            </div>

            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-3">Thuế giá trị gia tăng</h2>

                @if($tax['enabled'])
                    <p class="text-muted small mb-3">
                        Giá niêm yết <strong>đã bao gồm thuế</strong>. Đổi mức ở đây không làm
                        khách phải trả thêm — hệ thống chỉ tách phần thuế ra để ghi vào đơn
                        và báo cáo. Khách không nhìn thấy con số này.
                    </p>
                @else
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        Tính thuế <strong>đang tắt</strong> ở <code>config/tax.php</code>.
                        Đơn mới sẽ không ghi số liệu thuế.
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Thuế suất (%)</label>
                        <input type="number" name="tax_rate_percent" step="0.001" min="0" max="99.999"
                               class="form-control @error('tax_rate_percent') is-invalid @enderror"
                               value="{{ old('tax_rate_percent', $tax['rate_percent']) }}">
                        <x-form-error name="tax_rate_percent"/>
                        <p class="form-text">Để trống = dùng mức mặc định trong cấu hình.</p>
                    </div>

                    <div class="col-md-8 d-flex align-items-end">
                        <p class="text-muted small mb-2">
                            Mức thuế phụ thuộc mặt hàng và chính sách từng thời kỳ.
                            Hoa tươi và cây cảnh có trường hợp riêng.
                            <strong>Hỏi kế toán trước khi đổi</strong> — phần mềm không tư vấn thuế.
                        </p>
                    </div>
                </div>

                <h3 class="h6 fw-bold mt-4 mb-2">Nhóm thuế suất</h3>

                <p class="text-muted small mb-3">
                    Gán nhóm cho từng sản phẩm ở trang <strong>sửa sản phẩm</strong>.
                    Sản phẩm chưa gán nhóm dùng mức mặc định ở trên.
                </p>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nhóm</th>
                                <th style="width: 9rem;">Thuế suất (%)</th>
                                <th style="width: 7rem;">Đang dùng</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($taxClasses as $nhom)
                                <tr>
                                    <td class="fw-semibold">{{ $nhom->name }}</td>

                                    <td>
                                        <input type="number"
                                               name="tax_classes[{{ $nhom->id }}][rate_percent]"
                                               step="0.001" min="0" max="99.999"
                                               class="form-control form-control-sm"
                                               value="{{ old('tax_classes.' . $nhom->id . '.rate_percent', $nhom->rate === null ? '' : rtrim(rtrim(number_format((float) $nhom->rate * 100, 3, '.', ''), '0'), '.')) }}">
                                    </td>

                                    <td>
                                        <input type="hidden" name="tax_classes[{{ $nhom->id }}][is_active]" value="0">
                                        <input type="checkbox" class="form-check-input"
                                               name="tax_classes[{{ $nhom->id }}][is_active]" value="1"
                                               @checked(old('tax_classes.' . $nhom->id . '.is_active', $nhom->is_active))>
                                    </td>

                                    <td class="o-dai text-muted small">{{ $nhom->note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="form-text mt-2">
                    Để trống ô thuế suất nghĩa là <strong>không thuộc diện chịu VAT</strong> —
                    khác với <strong>chịu thuế suất 0%</strong> (gõ số <code>0</code>).
                    Hai trường hợp này ghi khác nhau trên hoá đơn.
                </p>

            </div>


            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-3">Hình thức thanh toán</h2>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead>
                            <tr>
                                <th>Hình thức</th>
                                <th>Loại</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paymentMethods as $pm)
                                <tr>
                                    <td class="o-dai">
                                        <strong>{{ $pm['label'] }}</strong>
                                        <div class="text-muted small">{{ $pm['hint'] }}</div>
                                    </td>
                                    <td class="text-muted small">
                                        {{ $pm['online'] ? 'Cổng trực tuyến' : 'Trả trực tiếp' }}
                                    </td>
                                    <td>
                                        @if($pm['ready'])
                                            <span class="status-chip status-chip--success">Đang dùng</span>
                                        @else
                                            <span class="status-chip status-chip--neutral">Chưa cấu hình</span>
                                            <div class="text-muted small">
                                                Thiếu khoá <code>{{ strtoupper($pm['gateway'] ?? '') }}_*</code> trong <code>.env</code>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-muted small mb-0">
                    Thêm một cổng mới cần ba việc: khai <code>case</code> trong
                    <code>PaymentMethod</code>, viết lớp cài đặt
                    <code>PaymentGateway</code>, và điền khoá vào <code>.env</code>.
                    Không sửa được từ giao diện vì khoá bí mật không được nằm trong cơ sở dữ liệu.
                </p>

            </div>

            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-1">Cam kết dịch vụ</h2>

                <p class="admin-page-subtitle mb-3">
                    Hiện ở trang chi tiết sản phẩm. Chỉ điền những gì cửa hàng
                    thực sự làm được — bỏ trống dòng nào thì dòng đó không hiện.
                </p>

                @for($i = 0; $i < $commitmentMax; $i++)
                    @php $row = $commitments[$i] ?? ['icon' => 'check-circle', 'title' => '', 'note' => '']; @endphp

                    <div class="row g-2 mb-2 align-items-start">

                        <div class="col-md-3">
                            <label class="visually-hidden" for="cm-icon-{{ $i }}">Biểu tượng dòng {{ $i + 1 }}</label>
                            <select name="commitments[{{ $i }}][icon]" id="cm-icon-{{ $i }}" class="form-select">
                                @foreach($commitmentIcons as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(old("commitments.$i.icon", $row['icon']) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="visually-hidden" for="cm-title-{{ $i }}">Tiêu đề dòng {{ $i + 1 }}</label>
                            <input type="text" name="commitments[{{ $i }}][title]" id="cm-title-{{ $i }}"
                                   value="{{ old("commitments.$i.title", $row['title']) }}"
                                   class="form-control @error("commitments.$i.title") is-invalid @enderror"
                                   maxlength="80" placeholder="Giao trong ngày">
                            <x-form-error name="commitments.{{ $i }}.title" />
                        </div>

                        <div class="col-md-5">
                            <label class="visually-hidden" for="cm-note-{{ $i }}">Ghi chú dòng {{ $i + 1 }}</label>
                            <input type="text" name="commitments[{{ $i }}][note]" id="cm-note-{{ $i }}"
                                   value="{{ old("commitments.$i.note", $row['note']) }}"
                                   class="form-control" maxlength="120"
                                   placeholder="Giải thích ngắn">
                        </div>

                    </div>
                @endfor

            </div>

            <button type="submit" class="btn btn-primary-brand px-4">
                Lưu cài đặt
            </button>

        </form>

    </div>

    <div class="col-lg-4">

        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-2">Theme và Khuyến mại là hai thứ khác nhau</h2>
            <p class="text-muted small mb-2">
                <strong>Theme</strong> quyết định website <em>trông như thế nào</em>
                (màu, artwork, hiệu ứng).
            </p>
            <p class="text-muted small mb-0">
                <strong>Chương trình khuyến mại</strong> quyết định
                <em>đang bán gì</em> — nội dung banner và danh sách sản phẩm giảm
                giá lấy từ
                <a href="{{ route('admin.promotions.index') }}">mục Khuyến mại</a>,
                không phụ thuộc theme đang bật.
            </p>
        </div>

    </div>

</div>

<script>
(function () {
    document.querySelectorAll('#themePicker input[name=theme]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (window.ThemeManager) {
                window.ThemeManager.applyTheme(radio.value, radio.dataset.themeEffect || null);
            }

            document.querySelectorAll('[data-theme-option]').forEach(function (label) {
                label.classList.toggle(
                    'is-selected',
                    label.getAttribute('data-theme-option') === radio.value
                );
            });
        });
    });
})();
</script>

@endsection
