@extends('layouts.app')

@section('title', 'Danh mục')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Danh mục']]" />

        <div class="section-header">
            <div>
                {{--
                    BỎ nhãn "Danh mục": breadcrumb ngay trên đã viết
                    "Danh mục" và tiêu đề ngay dưới viết "Tất cả danh
                    mục". Một nhãn chỉ chép lại chữ bên cạnh thì nó không
                    phải nhãn, nó là tiếng ồn.
                --}}
                <h1 class="text-h1 section-header__title">Tất cả danh mục</h1>
            </div>
        </div>

        @if($categories->isEmpty())
            <x-site.empty-state title="Chưa có danh mục nào" />
        @else
            <div class="row g-4">
                @foreach($categories as $category)
                    <div class="col-6 col-md-4 col-lg-3">
                        <x-category.card :category="$category" />
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</section>

@endsection
