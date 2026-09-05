@extends('layouts.app')

@section('title', $journal->title)

@section('content')

@php
    /*
     * TRANG SỔ — GHÉP TỪ CÁC KHỐI DO LOẠI SỔ KHAI BÁO.
     * ============================================================
     * Tệp này KHÔNG biết sổ mục tiêu khác sổ giá ở chỗ nào. Nó chỉ đọc
     * `JournalKind::panels()` rồi vẽ đúng danh sách đó, theo đúng thứ tự
     * đó.
     *
     * Vì sao không viết `@if($journal->kind === Price)` ở đây: năm loại
     * sổ × sáu khối là ba mươi nhánh điều kiện trong một tệp Blade, và
     * thêm loại sổ thứ sáu thì phải đọc lại cả ba mươi. Đưa quyết định về
     * enum thì thêm loại sổ mới chỉ là thêm một dòng vào `panels()`.
     */
    $kind = $journal->kind;
    $tu = $kind->entryWords();
@endphp

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[
            ['label' => 'Nhật ký của tôi', 'url' => route('shop.journals.index')],
            ['label' => $journal->title],
        ]" />

        {{-- Bộ giao diện của sổ bọc quanh TOÀN BỘ nội dung: màu giấy, màu
             nhấn và hoa văn đều lấy từ đây. --}}
        <div class="journal-book {{ $journal->theme()->token() }}">

            @if($journal->theme()->pattern())
                <x-journal.pattern :pattern="$journal->theme()->pattern()" />
            @endif

            @if($journal->cover_image)
                {{-- Ảnh bìa nằm TRONG khung sổ, trên tiêu đề: nó thuộc về
                     quyển sổ, không phải một banner của trang. --}}
                <x-site.image :path="$journal->cover_image"
                              :alt="'Ảnh bìa sổ ' . $journal->title"
                              class="journal-book__cover" />
            @endif

            <div class="section-header journal-book__head">
                <div>
                    <span class="text-label section-header__eyebrow d-block">
                        {{ $kind->label() }}
                    </span>
                    <h1 class="text-h1 section-header__title">{{ $journal->title }}</h1>

                    @if($journal->description)
                        <p class="text-body-sm mt-2 mb-0" style="max-width: 62ch;">{{ $journal->description }}</p>
                    @endif

                    @if($journal->product)
                        <p class="text-body-sm mt-2 mb-0">
                            Gắn với
                            <a href="{{ route('shop.products.show', $journal->product) }}">{{ $journal->product->name }}</a>
                        </p>
                    @endif
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('shop.journals.edit', $journal) }}" class="btn btn-ghost">Sửa sổ</a>

                    <form method="POST" action="{{ route('shop.journals.archive', $journal) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-ghost">
                            {{ $journal->is_archived ? 'Đưa trở lại' : 'Lưu trữ' }}
                        </button>
                    </form>
                </div>
            </div>

            @if($journal->is_archived)
                <div class="alert alert-secondary py-2 px-3">
                    Sổ này đang ở mục lưu trữ — vẫn ghi thêm được bình thường.
                </div>
            @endif

            <div class="row g-4">

                {{-- ---------- CỘT TRÁI: các khối của loại sổ này ---------- --}}
                <div class="col-lg-7">
                    @foreach($kind->panels() as $panel)
                        @switch($panel)
                            @case('goal')
                                <x-journal.panel.goal :journal="$journal" />
                                @break

                            @case('milestones')
                                <x-journal.panel.milestones :journal="$journal" />
                                @break

                            @case('chart')
                                <x-journal.panel.chart
                                    :journal="$journal"
                                    :metric-names="$metricNames"
                                    :chart-metric="$chartMetric"
                                    :series="$series" />
                                @break

                            @case('photo-strip')
                                <x-journal.panel.photo-strip :journal="$journal" />
                                @break

                            @case('care-summary')
                                <x-journal.panel.care-summary :journal="$journal" />
                                @break

                            @case('price-stats')
                                <x-journal.panel.price-stats :journal="$journal" />
                                @break

                            @case('price-table')
                                <x-journal.panel.price-table :journal="$journal" />
                                @break

                            @case('rating')
                                <x-journal.panel.rating :journal="$journal" />
                                @break

                            @case('findings')
                                <x-journal.panel.findings :journal="$journal" />
                                @break

                            @case('timeline')
                                <x-journal.panel.timeline :journal="$journal" />
                                @break
                        @endswitch
                    @endforeach
                </div>

                {{-- ---------- CỘT PHẢI: ghi thêm ---------- --}}
                <div class="col-lg-5">
                    <div class="surface-card p-4 journal-compose">
                        <h2 class="text-h4 mb-3">{{ $tu['add'] }}</h2>

                        <x-journal.entry-form :journal="$journal" :conditions="$conditions" />
                    </div>
                </div>

            </div>
        </div>

    </div>
</section>

@endsection
