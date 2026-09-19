@extends('layouts.admin')

@section('title', 'Thêm người dùng')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Thêm người dùng</h1>
    <p class="admin-page-subtitle">Tài khoản do quản trị tạo được coi là đã xác thực email.</p>
</div>

<form method="POST" action="{{ route('admin.users.store') }}">
    @csrf
    @include('admin.users._form')
</form>

@endsection
