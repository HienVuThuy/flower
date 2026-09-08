@extends('layouts.admin')

@section('title', 'Cài đặt')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Cài đặt hệ thống</h1>
    <p class="admin-page-subtitle">Đổi theme và thông tin liên hệ — áp dụng ngay, không cần deploy lại.</p>
</div>

<div class="row g-4">

    <div class="col-lg-8">

        {{-- enctype bắt buộc: không có thì ô chọn ảnh hero gửi lên rỗng. --}}
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

                                {{-- Màu xem trước lấy từ config/theme.php, không hard-code trong JS --}}
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

            {{-- ============ ẢNH HERO THEO TỪNG THEME ============ --}}
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

                    {{-- <details> để trang không bị dài; không cần JavaScript. --}}
                    <details class="hero-manager" @if($activeTheme === $themeKey) open @endif>

                        <summary class="hero-manager__summary">
                            <span class="fw-semibold">{{ $theme['label'] }}</span>
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

                                            {{-- Đường dẫn đi kèm để server biết ảnh nào được
                                                 giữ lại; server vẫn đối chiếu với bản ghi cũ
                                                 nên sửa ô ẩn này không chèn được ảnh lạ. --}}
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

            {{--
                ============================================================
                NHẬN DIỆN CỬA HÀNG — tên, dòng phụ, logo.
                ============================================================
                Trước đây tên cửa hàng viết cứng ở 17 chỗ trong mã nguồn
                (header, chân trang, sáu mẫu thư, tiêu đề mọi trang, layout
                quản trị). Đổi tên nghĩa là sửa 17 tệp và chắc chắn bỏ sót
                một chỗ — thường là một mẫu thư, tức là chỗ khách nhìn thấy
                mà chủ cửa hàng thì không.
            --}}
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
                            {{-- Xem trước LOGO ĐANG DÙNG, không phải một ô trống.
                                 Chưa tải logo riêng thì hiện hình vẽ mặc định —
                                 admin thấy ngay cửa hàng mình đang trông thế nào. --}}
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
                            {{-- KHÔNG nhận SVG: SVG là XML và chạy được JavaScript bên
                                 trong — một tệp logo trở thành lỗ XSS trên mọi trang. --}}
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

                    {{--
                        TỈNH TÁCH RIÊNG KHỎI ĐỊA CHỈ, và phải CHỌN từ danh sách.

                        Phí giao tra theo đúng chuỗi tên tỉnh. Nếu để hệ thống
                        tự dò tên tỉnh trong ô địa chỉ gõ tay thì "Hà Nội" và
                        "TP Hà Nội" ra hai kết quả khác nhau — mà cái sai lại
                        rơi vào vùng mặc định, tức mọi đơn nội thành bị tính
                        giá tỉnh xa.
                    --}}
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

            {{--
                ============================================================
                TIỀN TỆ
                ============================================================
                Bốn tham số này quyết định MỌI số tiền trên toàn hệ thống
                hiện ra thế nào — thẻ sản phẩm, giỏ hàng, đơn hàng, thư xác
                nhận, trang quản trị. Trước đây chúng viết cứng ở 49 chỗ
                trong 19 tệp, với ba biến thể ký hiệu khác nhau.

                Dấu ngăn nghìn KHÔNG có ô riêng: nó suy ra từ mã tiền tệ.
                Cho admin tự chọn từng dấu là mở đường cho những tổ hợp
                không tồn tại ở đâu cả ("1.234.56").
            --}}
            <div class="admin-panel p-4 mb-4">

                <h2 class="h6 fw-bold mb-3">Đơn vị tiền tệ</h2>

                <p class="text-muted small mb-3">
                    Áp dụng cho mọi số tiền: thẻ sản phẩm, giỏ hàng, đơn hàng,
                    thư gửi khách và trang quản trị.
                    Đang hiển thị: <strong>{{ \App\Services\Shop\Money::format(260000) }}</strong>
                </p>

                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label">Mã tiền tệ</label>
                        <input type="text" name="currency_code"
                               class="form-control text-uppercase @error('currency_code') is-invalid @enderror"
                               value="{{ old('currency_code', $currency['currency_code']) }}"
                               maxlength="3" required>
                        <x-form-error name="currency_code"/>
                        <p class="form-text">Ba chữ in hoa, ví dụ VND.</p>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ký hiệu</label>
                        <input type="text" name="currency_symbol"
                               class="form-control @error('currency_symbol') is-invalid @enderror"
                               value="{{ old('currency_symbol', $currency['currency_symbol']) }}"
                               maxlength="5" required>
                        <x-form-error name="currency_symbol"/>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Vị trí ký hiệu</label>
                        <select name="currency_position" class="form-select">
                            <option value="after" @selected(old('currency_position', $currency['currency_position']) === 'after')>
                                Sau số — 260.000₫
                            </option>
                            <option value="before" @selected(old('currency_position', $currency['currency_position']) === 'before')>
                                Trước số — ₫260.000
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Số chữ số thập phân</label>
                        <input type="number" name="currency_decimals" min="0" max="4"
                               class="form-control @error('currency_decimals') is-invalid @enderror"
                               value="{{ old('currency_decimals', $currency['currency_decimals']) }}" required>
                        <x-form-error name="currency_decimals"/>
                        <p class="form-text">VND để 0 (không có xu).</p>
                    </div>

                </div>

            </div>

            {{--
                ============================================================
                THUẾ
                ============================================================
                GIÁ NIÊM YẾT ĐÃ BAO GỒM VAT. Đổi con số ở đây KHÔNG làm
                khách phải trả thêm một đồng nào và không con số nào trên
                giao diện khách hàng thay đổi — nó chỉ đổi cách hệ thống
                TÁCH phần thuế ra để ghi vào đơn.
            --}}
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
                        {{-- Đây là ranh giới trách nhiệm, không phải câu cảnh
                             báo cho có: phần mềm không biết mặt hàng của cửa
                             hàng chịu thuế suất nào. --}}
                        <p class="text-muted small mb-2">
                            Mức thuế phụ thuộc mặt hàng và chính sách từng thời kỳ.
                            Hoa tươi và cây cảnh có trường hợp riêng.
                            <strong>Hỏi kế toán trước khi đổi</strong> — phần mềm không tư vấn thuế.
                        </p>
                    </div>
                </div>

                {{--
                    ============ NHÓM THUẾ SUẤT ============

                    MỘT MỨC CHO CẢ CỬA HÀNG LÀ KHÔNG ĐỦ. Cửa hàng bán hoa
                    tươi, cây giống, chậu sứ và giá thể — bốn thứ có bản
                    chất thuế khác nhau. Ô "Thuế suất" bên trên chỉ còn là
                    mức MẶC ĐỊNH: nó áp cho phí vận chuyển và cho những
                    sản phẩm chưa được phân loại.

                    Gán nhóm cho từng sản phẩm ở trang sửa sản phẩm; ở đây
                    chỉ chỉnh mức của từng nhóm khi chính sách thay đổi.
                --}}
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
                                        {{--
                                            Ô ẩn đi kèm: trình duyệt KHÔNG gửi
                                            checkbox chưa tích, nên không có nó
                                            thì bỏ tích một nhóm sẽ không lưu
                                            được — ô biến mất khỏi dữ liệu gửi
                                            lên và máy chủ hiểu là "không đổi".
                                        --}}
                                        <input type="hidden" name="tax_classes[{{ $nhom->id }}][is_active]" value="0">
                                        <input type="checkbox" class="form-check-input"
                                               name="tax_classes[{{ $nhom->id }}][is_active]" value="1"
                                               @checked(old('tax_classes.' . $nhom->id . '.is_active', $nhom->is_active))>
                                    </td>

                                    <td class="text-muted small">{{ $nhom->note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{--
                    Ý NGHĨA CỦA Ô TRỐNG Ở ĐÂY KHÁC Ô "THUẾ SUẤT" BÊN TRÊN,
                    và đó là một cái bẫy thật nên phải nói ra:

                        ô trên  — trống = "dùng mức mặc định trong cấu hình"
                        bảng này — trống = "KHÔNG thuộc diện chịu VAT"

                    "Không chịu VAT" khác "chịu thuế suất 0%": hàng 0% vẫn
                    là hàng chịu thuế và vẫn lên hoá đơn với dòng thuế suất
                    0%. Muốn 0% thì gõ số 0, đừng để trống.
                --}}
                <p class="form-text mt-2">
                    Để trống ô thuế suất nghĩa là <strong>không thuộc diện chịu VAT</strong> —
                    khác với <strong>chịu thuế suất 0%</strong> (gõ số <code>0</code>).
                    Hai trường hợp này ghi khác nhau trên hoá đơn.
                </p>

            </div>


            {{--
                ============================================================
                HÌNH THỨC THANH TOÁN — bảng TRẠNG THÁI, không có công tắc.
                ============================================================
                Một cổng chỉ dùng được khi có đủ khoá bí mật trong .env.
                Cho admin bật một cổng chưa cấu hình là dựng ra lựa chọn
                hỏng giữa đường, sau khi khách đã điền hết địa chỉ.

                Bảng này trả lời câu "vì sao MoMo chưa hiện ra ở bước thanh
                toán" — thứ mà trước đây không chỗ nào trong giao diện trả
                lời được.
            --}}
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
                                    <td>
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

                {{--
                    Đây là LỜI HỨA của cửa hàng, không phải chữ trang trí.
                    Cố ý không cài sẵn câu mẫu nào ("giao 2h", "tươi 3+
                    ngày"...) — chỉ chủ cửa hàng mới biết mình làm được gì.
                    Để trống hết thì trang sản phẩm không hiện mục này.
                --}}
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
                                   maxlength="80" placeholder="Ví dụ: Giao nội thành trong ngày">
                            <x-form-error name="commitments.{{ $i }}.title" />
                        </div>

                        <div class="col-md-5">
                            <label class="visually-hidden" for="cm-note-{{ $i }}">Ghi chú dòng {{ $i + 1 }}</label>
                            <input type="text" name="commitments[{{ $i }}][note]" id="cm-note-{{ $i }}"
                                   value="{{ old("commitments.$i.note", $row['note']) }}"
                                   class="form-control" maxlength="120"
                                   placeholder="Giải thích ngắn (không bắt buộc)">
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
            // Đi qua ThemeManager để hiệu ứng của theme trước đó được
            // destroy() sạch trước khi theme mới nạp — kể cả khi admin
            // bấm thử liên tục nhiều theme ở đây.
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
