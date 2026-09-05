@extends('layouts.admin')

@section('title', 'Đề xuất giá & ưu đãi')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Đề xuất giá &amp; ưu đãi</h1>
        <p class="admin-page-subtitle mb-0">
            Mọi đề xuất dưới đây đều kèm con số đã sinh ra nó.
            Sản phẩm chưa đủ dữ liệu thì <strong>không có đề xuất</strong> — không đoán thay.
        </p>
    </div>

    {{-- Chọn cửa sổ quan sát bằng liên kết thường, không cần JavaScript. --}}
    <div class="d-flex flex-wrap gap-2">
        @foreach($windows as $value => $label)
            <a href="{{ route('admin.pricing-advisor.index', ['ngay' => $value]) }}"
               class="btn btn-sm {{ $window === $value ? 'btn-primary-brand' : 'btn-outline-admin' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

{{-- ============ MẪU DỮ LIỆU ĐANG LỚN HAY NHỎ ============ --}}
@if($result['thin_data'])
    {{--
        NÓI TRƯỚC KHI ADMIN ĐỌC ĐỀ XUẤT, không phải chú thích nhỏ ở cuối
        trang.

        Cửa hàng mới có vài chục đơn. Ở quy mô đó một đề xuất là gợi ý để
        đi kiểm tra, không phải kết luận để hành động ngay — và admin cần
        biết điều đó TRƯỚC khi đọc, chứ không phải sau khi đã đổi giá.
    --}}
    <div class="alert alert-warning d-flex gap-3 align-items-start">
        
        <div>
            <strong>Mẫu dữ liệu còn mỏng.</strong>
            Khoảng {{ $window }} ngày qua có <strong>{{ number_format($result['total_orders']) }} đơn</strong>
            (mức đủ tự tin là {{ $confidentOrders }}).
            Hãy đọc các đề xuất dưới đây như <em>chỗ đáng đi xem lại</em>, chưa phải kết luận.
        </div>
    </div>
@endif

<div class="admin-panel p-4 mb-4">
    <div class="row g-3 text-center">
        <div class="col-6 col-md-3">
            <div class="h4 mb-0">{{ number_format($result['examined']) }}</div>
            <div class="admin-page-subtitle">sản phẩm đã xét</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="h4 mb-0">{{ number_format($result['suggestions']->count()) }}</div>
            <div class="admin-page-subtitle">có đề xuất</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="h4 mb-0">{{ number_format($result['skipped_too_few_views']) }}</div>
            <div class="admin-page-subtitle">chưa đủ dữ liệu để nói</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="h4 mb-0">{{ number_format($result['total_orders']) }}</div>
            <div class="admin-page-subtitle">đơn đã đặt ({{ $window }} ngày)</div>
        </div>
    </div>

    <hr class="my-3">

    <p class="admin-page-subtitle mb-0">
        <strong>{{ number_format($result['skipped_too_few_views']) }} sản phẩm bị bỏ qua</strong>
        vì có dưới {{ $minViews }} lượt xem trong khoảng này. Với chúng, &ldquo;không ai mua&rdquo;
        chưa nói gì về giá — nó mới chỉ nói là gần như chưa ai nhìn thấy.
        Vấn đề ở đó là hiển thị, không phải giá.
    </p>
</div>

{{-- ============ DANH SÁCH ĐỀ XUẤT ============ --}}
@if($result['suggestions']->isEmpty())
    <div class="admin-panel p-4 mb-4">
        <x-site.empty-state
            title="Chưa có đề xuất nào"
            text="Không sản phẩm nào trong khoảng này có đủ số liệu để kết luận về giá. Đó là câu trả lời đúng, không phải lỗi." />
    </div>
@else
    <div class="d-flex flex-column gap-3 mb-4">
        @foreach($result['suggestions'] as $s)
            <div class="admin-panel p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <span class="status-pill status-pill--{{ $s->signal->badge() }}">
                            {{ $s->signal->label() }}
                        </span>
                        <h2 class="h6 fw-bold mt-2 mb-0">
                            <a href="{{ route('admin.products.edit', $s->product()) }}">{{ $s->product()->name }}</a>
                        </h2>
                        <div class="admin-page-subtitle">
                            {{ $s->product()->category?->name }}
                            @if($s->product()->base_price !== null)
                                · Giá hiện tại <strong><x-site.money :amount="$s->product()->base_price" /></strong>
                            @endif
                        </div>
                    </div>

                    <a href="{{ route('admin.promotions.create') }}" class="btn btn-sm btn-outline-admin">
                        Tạo chương trình
                    </a>
                </div>

                {{--
                    BẰNG CHỨNG ĐỨNG TRƯỚC LỜI KHUYÊN.

                    Đây là công cụ khuyên đổi giá bán. Admin phải kiểm lại
                    được kết luận mà không cần tin vào mã nguồn — nên con
                    số đọc trước, ý kiến đọc sau.
                --}}
                <ul class="mb-2 ps-3">
                    @foreach($s->evidence as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>

                @if($s->anchor)
                    <p class="admin-page-subtitle mb-2">{{ $s->anchor }}</p>
                @endif

                <p class="mb-0"><strong>Cân nhắc:</strong> {{ $s->signal->suggestion() }}</p>
            </div>
        @endforeach
    </div>
@endif

{{-- ============ DỊP LỄ SẮP TỚI ============ --}}
<div class="admin-panel p-4 mb-4">
    <h2 class="h6 fw-bold mb-1">Dịp lễ sắp tới (90 ngày)</h2>
    <p class="admin-page-subtitle mb-3">
        Đối chiếu với các chương trình đang chạy hoặc đã lên lịch.
        Công cụ chỉ nhắc — nó không tự tạo chương trình, vì tạo chương trình là quyết định giá bán.
    </p>

    @if($occasions->isEmpty())
        <p class="mb-0 admin-page-subtitle">Không có dịp dương lịch nào trong 90 ngày tới.</p>
    @else
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Dịp</th>
                        <th>Ngày</th>
                        <th>Còn</th>
                        <th>Chương trình</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($occasions as $d)
                        <tr>
                            <td>
                                {{ $d['name'] }}
                                @if($d['note'])
                                    <div class="admin-page-subtitle">{{ $d['note'] }}</div>
                                @endif
                            </td>
                            <td>{{ $d['date']->format('d/m/Y') }}</td>
                            <td>{{ $d['days_away'] }} ngày</td>
                            <td>
                                @if($d['covered_by'])
                                    <a href="{{ route('admin.promotions.edit', $d['covered_by']) }}">
                                        {{ $d['covered_by']->name }}
                                    </a>
                                @else
                                    <span class="status-pill status-pill--warning">Chưa có</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ============ DỊP ÂM LỊCH ============ --}}
<div class="admin-panel p-4">
    <h2 class="h6 fw-bold mb-1">Dịp theo âm lịch</h2>
    {{--
        KHÔNG ĐOÁN NGÀY DƯƠNG.

        Ngày dương của các dịp này đổi mỗi năm. Viết cứng một ngày là ghi
        một dữ kiện sai cho mọi năm trừ một năm — và sai một cách im lặng.
        Nên chỉ liệt kê để nhắc, không kết luận "đã có chương trình chưa",
        vì không biết ngày thì không kiểm được.
    --}}
    <p class="admin-page-subtitle mb-3">
        Ngày dương thay đổi theo từng năm nên hệ thống <strong>không tự tra</strong> và
        không kết luận dịp nào đã có chương trình. Đây là danh sách để nhắc — ngày cụ thể
        do bạn đặt khi tạo chương trình.
    </p>

    <div class="row g-3">
        @foreach($lunarOccasions as $d)
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <x-site.icon name="clock-history" class="flex-shrink-0 mt-1" />
                    <div>
                        <strong>{{ $d['name'] }}</strong>
                        <div class="admin-page-subtitle">{{ $d['lunar_note'] }}</div>
                        @if($d['note'])
                            <div class="admin-page-subtitle">{{ $d['note'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@endsection
