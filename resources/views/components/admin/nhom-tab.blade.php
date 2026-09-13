@props(['ten'])

{{--
    HÀNG TAB GỘP NHỮNG TRANG CÙNG MỘT VIỆC.
    ============================================================
    VÌ SAO: thanh bên từng có mười một mục chỉ riêng nhóm Cửa hàng — Tồn
    đầu kỳ, Trả hàng nhà cung cấp, Loại hoa, Chuyên mục cẩm nang… mỗi cái
    một dòng. Chúng đều là việc PHỤ của một trang chính, và người dùng tìm
    chúng ở chính trang đó chứ không đi dò thanh bên.

    Thanh bên giữ trang chính; các trang phụ thành tab ngay trên trang.

    MỘT CHỖ KHAI BÁO cho mọi nhóm: thêm một trang vào nhóm là thêm một dòng
    ở đây, không phải đi sửa từng trang trong nhóm.
--}}
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
