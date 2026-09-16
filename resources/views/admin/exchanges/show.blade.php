@extends('layouts.admin')

@section('title', 'Phiếu đổi ' . $phieu->code)

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">{{ $phieu->code }}</h1>
        <p class="admin-page-subtitle mb-0">
            {{ $phieu->reason->label() }}
            &middot; lập lúc <x-site.time :at="$phieu->created_at" />
            @if($phieu->createdBy)
                &middot; {{ $phieu->createdBy->name }}
            @endif
        </p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge text-bg-{{ $phieu->status->tone() }}">{{ $phieu->status->label() }}</span>

        @if($phieu->order)
            <a data-admin-link href="{{ route('admin.orders.show', $phieu->order) }}"
               class="btn btn-sm btn-outline-admin">
                Đơn {{ $phieu->order->order_number }}
            </a>
        @endif
    </div>
</div>

<p class="admin-page-subtitle">{{ $phieu->status->hint() }}</p>

<div class="row g-3">

    <div class="col-lg-7">

        <div class="admin-panel p-4 mb-3">
            <h2 class="h6 fw-bold mb-3">Khách trả về</h2>

            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Món</th>
                            <th scope="col">SL</th>
                            <th scope="col">Giá đã trả</th>
                            <th scope="col">Thành tiền</th>
                            <th scope="col">Bán lại được</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($phieu->hangTra as $dong)
                            <tr>
                                <td>{{ $dong->ten_hang }}</td>
                                <td>{{ $dong->quantity }}</td>
                                <td>{{ $tien($dong->unit_price) }}</td>
                                <td>{{ $tien($dong->thanhTien()) }}</td>
                                <td>
                                    @if($phieu->status === \App\Enums\ExchangeStatus::ChoNhan)
                                        <span class="admin-page-subtitle">chưa nhận</span>
                                    @else
                                        {{ $dong->restock ? 'Có — đã cộng lại kho' : 'Không' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h2 class="h6 fw-bold mb-3">Gửi cho khách</h2>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Món</th>
                            <th scope="col">SL</th>
                            <th scope="col">Giá hôm đổi</th>
                            <th scope="col">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($phieu->hangMoi as $dong)
                            <tr>
                                <td>{{ $dong->ten_hang }}</td>
                                <td>{{ $dong->quantity }}</td>
                                <td>{{ $tien($dong->unit_price) }}</td>
                                <td>{{ $tien($dong->thanhTien()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($phieu->note)
                <p class="admin-page-subtitle small mt-3 mb-0">Ghi chú: {{ $phieu->note }}</p>
            @endif
        </div>

    </div>

    <div class="col-lg-5">

        <div class="admin-panel p-4 mb-3">
            <h2 class="h6 fw-bold mb-3">Tiền</h2>

            <dl class="admin-detail-list mb-3">
                <div><dt>Hàng trả về</dt><dd>{{ $tien($phieu->tien_hang_tra) }}</dd></div>
                <div><dt>Hàng gửi đi</dt><dd>{{ $tien($phieu->tien_hang_moi) }}</dd></div>
                <div>
                    <dt>Phí ship chiều đổi</dt>
                    <dd>
                        {{ $tien($phieu->phi_ship) }}
                        <span class="d-block admin-page-subtitle small">{{ $phieu->reason->hint() }}</span>
                    </dd>
                </div>
            </dl>

            @if(bccomp((string) $phieu->chenh_lech, '0', 2) === 0)
                <p class="mb-0"><strong>Đổi ngang</strong> — không ai phải trả thêm gì.</p>
            @elseif($phieu->cuaHangNoLai())
                <p class="mb-1">
                    <strong>Cửa hàng trả lại khách {{ $tien(abs((float) $phieu->chenh_lech)) }}</strong>
                </p>
                @if($phieu->refund)
                    <p class="admin-page-subtitle small mb-0">
                        Đã lập phiếu hoàn tiền <strong>{{ $phieu->refund->code }}</strong>
                        ({{ $phieu->refund->status->label() }}).
                        Tiền chỉ thật sự đi khi có người xác nhận ở trang đơn.
                    </p>
                @endif
            @else
                <p class="mb-1"><strong>Khách bù thêm {{ $tien($phieu->chenh_lech) }}</strong></p>
                <p class="admin-page-subtitle small mb-0">
                    Đã thu {{ $tien($phieu->da_thu) }}
                    &middot; còn {{ $tien($phieu->conPhaiThu()) }}
                </p>
            @endif
        </div>

        @unless($phieu->status->daXong())
            <div class="admin-panel p-4 mb-3">
                <h2 class="h6 fw-bold mb-3">Việc tiếp theo</h2>

                @if($phieu->status === \App\Enums\ExchangeStatus::ChoNhan)
                    <form method="POST" action="{{ route('admin.exchanges.receive', $phieu) }}">
                        @csrf
                        @method('PATCH')

                        <p class="admin-page-subtitle small">
                            Đánh dấu món nào còn bán lại được — chỉ những món đó mới
                            được cộng lại vào kho. Chậu vỡ khách gửi về không phải hàng tồn.
                        </p>

                        @foreach($phieu->hangTra as $dong)
                            <label class="d-flex align-items-center gap-2 mb-2">
                                <input type="checkbox" class="form-check-input"
                                       name="ban_lai[{{ $dong->id }}]" value="1">
                                <span>{{ $dong->ten_hang }} &times; {{ $dong->quantity }}</span>
                            </label>
                        @endforeach

                        <button type="submit" class="btn btn-sm btn-primary-brand mt-2">
                            Đã nhận hàng trả về
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.exchanges.complete', $phieu) }}">
                        @csrf
                        @method('PATCH')

                        @if(bccomp($phieu->conPhaiThu(), '0', 2) > 0)
                            <div class="mb-3">
                                <label class="form-label" for="da-thu">
                                    Đã thu của khách (tối đa {{ $tien($phieu->conPhaiThu()) }})
                                </label>
                                <input type="number" id="da-thu" name="da_thu"
                                       class="form-control form-control-sm" style="max-width:12rem"
                                       min="0" max="{{ (int) $phieu->conPhaiThu() }}" value="0">
                                <x-form-error name="da_thu"/>
                            </div>
                        @else
                            <p class="admin-page-subtitle small">Không còn khoản nào phải thu.</p>
                        @endif

                        <button type="submit" class="btn btn-sm btn-primary-brand">
                            Đã gửi hàng mới — hoàn tất phiếu
                        </button>
                    </form>
                @endif
            </div>

            <div class="admin-panel p-4">
                <h2 class="h6 fw-bold mb-2">Huỷ phiếu</h2>
                <p class="admin-page-subtitle small">
                    Số hàng mới đang giữ trong kho sẽ được trả lại.
                </p>

                <form method="POST" action="{{ route('admin.exchanges.cancel', $phieu) }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-2">
                        <label class="form-label" for="ly-do-huy">Lý do huỷ</label>
                        <input type="text" id="ly-do-huy" name="ly_do" class="form-control form-control-sm"
                               maxlength="250" required>
                        <x-form-error name="ly_do"/>
                    </div>

                    <button type="submit" class="btn btn-sm btn-outline-danger">Huỷ phiếu</button>
                </form>
            </div>
        @else
            <div class="admin-panel p-4">
                <dl class="admin-detail-list mb-0">
                    @if($phieu->nhan_hang_at)
                        <div>
                            <dt>Nhận hàng trả</dt>
                            <dd><x-site.time :at="$phieu->nhan_hang_at" /></dd>
                        </div>
                    @endif
                    @if($phieu->hoan_tat_at)
                        <div>
                            <dt>Hoàn tất</dt>
                            <dd><x-site.time :at="$phieu->hoan_tat_at" /></dd>
                        </div>
                    @endif
                    @if($phieu->huy_at)
                        <div>
                            <dt>Đã huỷ</dt>
                            <dd>
                                <x-site.time :at="$phieu->huy_at" />
                                <span class="d-block admin-page-subtitle">{{ $phieu->ly_do_huy }}</span>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endunless

    </div>

</div>

@endsection
