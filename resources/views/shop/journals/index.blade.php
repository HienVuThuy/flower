@extends('layouts.app')

@section('title', 'Nhật ký của tôi')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Nhật ký của tôi']]" />

        <div class="section-header">
            <div>
                <h1 class="text-h1 section-header__title">Nhật ký của tôi</h1>
                {{--
                    NÓI THẲNG LÀ RIÊNG TƯ, ngay ở dòng đầu.

                    Người ta chỉ ghi thật khi tin rằng không ai khác đọc.
                    Để họ tự đoán thì họ sẽ ghi dè chừng, và một quyển nhật
                    ký ghi dè chừng thì vô dụng với chính người viết.
                --}}
                <p class="text-body-sm mt-2 mb-0" style="max-width: 60ch;">
                    Sổ riêng của bạn — theo dõi cây lớn lên, đặt mục tiêu, ghi giá,
                    hay chỉ đơn giản là chép lại những gì bạn quan sát được.
                    <strong>Chỉ mình bạn đọc được.</strong> Cửa hàng không dùng nội dung
                    ở đây cho gợi ý sản phẩm hay bất kỳ thống kê nào.
                </p>
            </div>

            <a href="{{ route('shop.journals.create') }}" class="btn btn-primary-brand">
                <x-site.icon name="plus" /> Tạo sổ mới
            </a>
        </div>

        @if($soLuuTru > 0 || $dangXemLuuTru)
            <div class="filter-chip-group mb-4">
                <a href="{{ route('shop.journals.index') }}"
                   class="filter-chip {{ ! $dangXemLuuTru ? 'is-active' : '' }}">Đang dùng</a>
                <a href="{{ route('shop.journals.index', ['luu-tru' => 1]) }}"
                   class="filter-chip {{ $dangXemLuuTru ? 'is-active' : '' }}">
                    Lưu trữ <span class="filter-chip__count">{{ $soLuuTru }}</span>
                </a>
            </div>
        @endif

        @if($journals->isEmpty())

            {{--
                MÀN HÌNH TRỐNG PHẢI DẠY CÁCH DÙNG, không chỉ nói "chưa có gì".

                Đây là tính năng người dùng chưa từng thấy ở một trang bán
                hoa. Một dòng "Chưa có sổ nào" rồi để đó thì họ đóng tab.
                Liệt kê các loại sổ kèm câu giải thích là cách rẻ nhất để
                họ hiểu mình có thể làm gì với nó.
            --}}
            <div class="surface-card p-4">
                <h2 class="text-h3 mb-2">Chưa có sổ nào</h2>
                <p class="text-body-sm">Chọn một kiểu để bắt đầu — đổi được sau, không khoá gì cả.</p>

                <div class="row g-3 mt-2">
                    @foreach($kinds as $kind)
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('shop.journals.create', ['kind' => $kind->value]) }}"
                               class="journal-card">
                                <span class="journal-card__kind">
                                    <x-site.icon :name="$kind->icon()" /> {{ $kind->label() }}
                                </span>
                                <span class="text-body-sm">{{ $kind->hint() }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

        @else

            <div class="row g-3">
                @foreach($journals as $journal)
                    <div class="col-md-6 col-lg-4">
                        {{--
                            THẺ SỔ MANG GIAO DIỆN CỦA CHÍNH QUYỂN SỔ ĐÓ.

                            Bộ giao diện là thứ người dùng chọn để phân
                            biệt sổ này với sổ kia. Nếu nó chỉ hiện khi đã
                            mở sổ ra thì nó không giúp được gì ở đúng chỗ
                            cần phân biệt nhất — màn hình danh sách.
                        --}}
                        <a href="{{ route('shop.journals.show', $journal) }}"
                           class="journal-card {{ $journal->theme()->token() }}">

                            @if($journal->cover_image)
                                <x-site.image :path="$journal->cover_image" alt=""
                                              class="journal-card__cover" />
                            @endif

                            <span class="journal-card__kind">
                                <x-site.icon :name="$journal->kind->icon()" /> {{ $journal->kind->label() }}
                            </span>

                            <span class="journal-card__title">{{ $journal->title }}</span>

                            @if($journal->product)
                                <span class="text-body-sm">Gắn với: {{ $journal->product->name }}</span>
                            @endif

                            @if($journal->description)
                                <span class="text-body-sm">{{ Str::limit($journal->description, 90) }}</span>
                            @endif

                            <span class="journal-card__meta">
                                {{-- Mỗi loại sổ gọi một trang nhật ký bằng một từ
                                     khác nhau: "lần khảo giá" ở sổ giá, "lần cập
                                     nhật" ở sổ mục tiêu. Dùng chung một từ cho cả
                                     năm là tiết kiệm chữ bằng cách làm giao diện
                                     nói không đúng việc. --}}
                                {{ $journal->entries_count }} {{ $journal->kind->entryWords()['one'] }}
                                @if($journal->entries_count > 0)
                                    · sửa <x-site.time :at="$journal->updated_at" relative />
                                @endif
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>

        @endif

    </div>
</section>

@endsection
