@extends('layouts.admin')

@section('title', 'Quà theo chương trình')

@section('content')

<x-admin.promo-tabs />

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Quà theo chương trình</h1>
        <p class="admin-page-subtitle mb-0">
            Quà theo sự kiện: giới hạn suất, thời gian, hạng thành viên, đơn đầu tiên, đơn từ một số tiền. Mỗi chương trình tặng một bộ quà mỗi đơn.
            Quà mặc định của từng sản phẩm cấu hình ở
            <a data-admin-link href="{{ route('admin.product-gifts.index') }}">Quà tặng kèm sản phẩm</a>.
        </p>
    </div>
    <a data-admin-link href="{{ route('admin.gift-campaigns.create') }}" class="btn btn-primary-brand">Tạo chương trình quà</a>
</div>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Chương trình</th>
                    <th scope="col">Quà</th>
                    <th scope="col">Điều kiện</th>
                    <th scope="col">Đã phát</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($chuongTrinh as $ct)
                    <tr data-chuong-trinh-qua="{{ $ct->id }}">
                        <td>{{ $ct->name }}</td>
                        <td>{{ $ct->giftItem?->name }} × {{ $ct->gift_quantity }}</td>
                        <td class="small">
                            @if($ct->min_order_amount !== null)
                                Đơn từ {{ \App\Services\Shop\Money::format((string) $ct->min_order_amount) }}<br>
                            @endif
                            @if($ct->minMemberTier)
                                Từ hạng {{ $ct->minMemberTier->name }}<br>
                            @endif
                            @if($ct->first_order_only)
                                Đơn đầu tiên<br>
                            @endif
                            @if($ct->per_user_limit !== null)
                                Mỗi tài khoản {{ $ct->per_user_limit }} lần
                            @endif
                        </td>
                        <td>{{ $ct->used_count }}{{ $ct->total_limit !== null ? ' / ' . $ct->total_limit : '' }}</td>
                        <td>
                            {{ $ct->status->label() }}
                            @if($ct->status === \App\Enums\PromotionStatus::Active && ! $ct->isRunning())
                                <span class="d-block admin-page-subtitle small">ngoài thời gian hoặc hết suất</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a data-admin-link href="{{ route('admin.gift-campaigns.edit', $ct) }}" class="btn btn-sm btn-outline-admin">Sửa</a>
                            <form method="POST" action="{{ route('admin.gift-campaigns.destroy', $ct) }}" class="d-inline"
                                  onsubmit="return confirm('Xoá chương trình này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5 text-muted">Chưa có chương trình quà nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $chuongTrinh->links() }}</div>

@endsection
