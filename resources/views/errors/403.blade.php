@extends('layouts.app')

@section('title', 'Không có quyền truy cập')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card text-center">

        <div class="auth-card__mark d-flex align-items-center justify-content-center" style="color: var(--semantic-danger);">
            <x-site.icon name="shield-lock" style="font-size: 2rem;" />
        </div>

        <h1 class="text-h3 mb-2">403 — Không có quyền truy cập</h1>

        <p class="text-body-sm text-muted mb-4">
            {{ $exception->getMessage() ?: 'Bạn không có quyền truy cập trang này.' }}
        </p>

        <a href="{{ route('welcome') }}" class="btn btn-primary-brand">
            Về trang chủ
        </a>

    </div>

</div>

@endsection
