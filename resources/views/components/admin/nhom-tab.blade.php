@props(['ten'])

{{-- HÀNG TAB GỘP NHỮNG TRANG CÙNG MỘT VIỆC. --}}
@php
    $nhomTab = [
        'nhap-kho' => [
            ['route' => 'admin.stock-receipts.index', 'khop' => 'admin.stock-receipts.*', 'nhan' => 'Phiếu nhập'],
            ['route' => 'admin.opening-stock.create', 'khop' => 'admin.opening-stock.*', 'nhan' => 'Tồn đầu kỳ'],
            ['route' => 'admin.supplier-returns.index', 'khop' => 'admin.supplier-returns.*', 'nhan' => 'Trả hàng nhà cung cấp'],
        ],
        'lo-hoa' => [
            ['route' => 'admin.flower-lots.index', 'khop' => 'admin.flower-lots.*', 'nhan' => 'Lô hoa'],
            ['route' => 'admin.flower-kinds.index', 'khop' => 'admin.flower-kinds.*', 'nhan' => 'Loại hoa thu mua'],
        ],
        'cam-nang' => [
            ['route' => 'admin.blog.index', 'khop' => 'admin.blog.*', 'nhan' => 'Bài viết'],
            ['route' => 'admin.blog-categories.index', 'khop' => 'admin.blog-categories.*', 'nhan' => 'Chuyên mục'],
        ],
        'bao-cao' => [
            ['route' => 'admin.reports.index', 'khop' => 'admin.reports.index', 'nhan' => 'Bảng số liệu'],
            ['route' => 'admin.reports.charts', 'khop' => 'admin.reports.charts', 'nhan' => 'Biểu đồ'],
        ],
        'cai-dat' => [
            ['route' => 'admin.settings.edit', 'khop' => 'admin.settings.*', 'nhan' => 'Cài đặt chung'],
            ['route' => 'admin.page-contents.edit', 'khop' => 'admin.page-contents.*', 'nhan' => 'Trang nội dung'],
        ],
    ];

    $cacTab = $nhomTab[$ten] ?? [];
@endphp

@if($cacTab !== [])
    <nav class="analytics-tabs mb-4" aria-label="Các trang cùng nhóm">
        @foreach($cacTab as $tab)
            @php $dangO = request()->routeIs($tab['khop']); @endphp
            <a data-admin-link href="{{ route($tab['route']) }}"
               class="analytics-tabs__tab {{ $dangO ? 'is-active' : '' }}"
               @if($dangO) aria-current="page" @endif>
                {{ $tab['nhan'] }}
            </a>
        @endforeach
    </nav>
@endif
