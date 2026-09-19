@extends('layouts.app')

@section('title', 'Nhắn tin với cửa hàng')

@section('content')

<section class="section-sm">
    <div class="container-shop chat-page">

        <x-site.breadcrumb :items="[['label' => 'Nhắn tin với cửa hàng']]" />

        <div class="surface-card p-4">
            <h1 class="text-h2 mb-1">Nhắn tin với cửa hàng</h1>
            <p class="text-caption mb-3">
                Nhân viên {{ \App\Services\Shop\StoreProfile::name() }} trả lời trong giờ làm việc. Tin mới hiện tự động, không cần tải lại trang.
            </p>

            <x-chat.thread :tin="$tinNhan" />
        </div>

    </div>
</section>

@endsection
