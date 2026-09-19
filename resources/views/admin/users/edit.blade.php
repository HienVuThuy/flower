@extends('layouts.admin')

@section('title', 'Sửa ' . $user->name)

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Sửa người dùng</h1>
    <p class="admin-page-subtitle">{{ $user->email }} · muốn đặt lại mật khẩu thì dùng "Quên mật khẩu" ở trang đăng nhập.</p>
</div>

<form method="POST" action="{{ route('admin.users.update', $user) }}">
    @csrf
    @method('PUT')
    @include('admin.users._form', ['user' => $user])
</form>

@endsection
