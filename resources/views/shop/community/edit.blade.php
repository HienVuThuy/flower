@extends('layouts.app')

@section('title', 'Sửa bài Góc cây')

@section('content')

<section class="section-sm">
    <div class="container-shop community-post">

        <x-site.breadcrumb :items="[['label' => 'Góc cây của bạn', 'url' => route('shop.community.index')], ['label' => 'Sửa bài']]" />

        <div class="surface-card p-4 p-md-5">
            <h1 class="text-h3 mb-2">Sửa bài của bạn</h1>

            {{-- NÓI TRƯỚC HỆ QUẢ: bài đã đăng sửa xong quay lại hàng chờ duyệt. --}}
            @if($post->isApproved() || $post->isHidden())
                <p class="composer__canhbao" data-canh-bao-duyet>
                    Bài đang hiển thị. Lưu thay đổi xong, bài sẽ được gửi duyệt lại trước khi hiện.
                </p>
            @elseif($post->isRejected() && $post->reject_reason)
                <p class="gc-bai__note">Lý do không được duyệt: {{ $post->reject_reason }}</p>
            @endif

            <form method="POST" action="{{ route('shop.community.update', $post->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                @include('shop.community.partials.composer-fields', ['post' => $post])

                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <x-community.emoji-picker :target="'#body-sua-' . $post->id" />

                    <div class="d-flex gap-2">
                        <a href="{{ route('shop.community.index', ['tab' => 'cua-toi']) }}" class="btn btn-ghost">Huỷ</a>
                        <button type="submit" class="btn btn-primary-brand">Lưu thay đổi</button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</section>

@endsection
