@extends('layouts.app')

@section('title', 'Cây gửi chăm hộ')

@section('content')

<section class="section">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Chăm cây hộ', 'url' => route('shop.boarding.index')], ['label' => 'Cây của tôi']]" />

        <div class="section-header">
            <div>
                <h1 class="text-h1 section-header__title">Cây gửi chăm hộ</h1>
            </div>
            <a href="{{ route('shop.boarding.index') }}" class="btn btn-ghost">Gửi thêm cây</a>
        </div>

        @if($cacPhieu->isEmpty())
            <x-site.empty-state title="Bạn chưa gửi cây nào" text="Đi xa hay qua Tết không giữ được cây? Gửi cửa hàng chăm hộ." />
        @else
            <div class="boarding-list">
                @foreach($cacPhieu as $p)
                    <a href="{{ route('shop.boarding.show', $p) }}" class="boarding-list__item surface-card" data-phieu-cham-ho="{{ $p->code }}">
                        <span>
                            <strong>{{ $p->plant_name }}</strong>
                            <span class="d-block text-caption">{{ $p->code }} · {{ $p->rate?->name }} · {{ $p->mode->label() }}</span>
                        </span>
                        <span class="text-end">
                            <span class="badge text-bg-{{ $p->status->tone() }}">{{ $p->status->label() }}</span>
                            @if($p->return_on)
                                <span class="d-block text-caption mt-1">Nhận lại {{ $p->return_on->format('d/m/Y') }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="mt-3">{{ $cacPhieu->links() }}</div>
        @endif

    </div>
</section>

@endsection
