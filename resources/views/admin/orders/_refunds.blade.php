{{-- HOÀN TIỀN của một đơn. --}}

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
    $daHoan = $order->refundedAmount();
    $giuCho = $order->reservedRefundAmount();
    $dangCho = bcsub($giuCho, $daHoan, 2);
    $conHoan = $order->refundableAmount();
@endphp

<div class="admin-panel mb-3" id="hoan-tien">

    <h2 class="h6 fw-bold mb-1">Hoàn tiền</h2>

    @if($order->refunds->isNotEmpty() || $refundBlocked === null)
        <p class="admin-page-subtitle mb-3">
            Khách đã trả {{ $tien($order->grand_total) }}
            &middot; đã hoàn {{ $tien($daHoan) }}
            @if(bccomp($dangCho, '0', 2) > 0)
                &middot; <span class="text-warning-emphasis">chưa rõ kết quả {{ $tien($dangCho) }}</span>
            @endif
            &middot; còn hoàn được <strong>{{ $tien($conHoan) }}</strong>
        </p>
    @endif

    @if($order->invoice && ($order->refunds->isNotEmpty() || $refundBlocked === null))
        <div class="alert alert-warning py-2 px-3 small">
            Đơn này có yêu cầu xuất hoá đơn GTGT ({{ $order->invoice->invoice_number }}).
            Hoàn tiền thì hoá đơn đã xuất cần được điều chỉnh bên phần mềm hoá đơn điện tử; hệ thống này không tự làm.
        </div>
    @endif

    @if($order->refunds->isNotEmpty())
        <div class="d-flex flex-column gap-2 mb-3">
            @foreach($order->refunds as $r)
                <div class="border rounded p-2 small">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <strong>{{ $r->code }}</strong>
                        <span class="status-pill status-pill--{{ $r->status->badge() }}">{{ $r->status->label() }}</span>
                    </div>

                    <div class="mt-1">
                        <strong>{{ $tien($r->amount) }}</strong>
                        &middot; {{ $r->method->label() }}
                        &middot; {{ $r->reason->label() }}
                    </div>

                    <div class="admin-page-subtitle">
                        <x-site.time :at="$r->created_at" /> &middot; {{ $r->actorLabel() }}
                        @if($r->reference)
                            &middot; mã giao dịch <span data-copy-value="{{ $r->reference }}">{{ $r->reference }}</span>
                        @endif
                    </div>

                    @if($r->note)
                        <div class="mt-1">{{ $r->note }}</div>
                    @endif

                    @if($r->status === \App\Enums\RefundStatus::Failed && ! empty($r->gateway_response['message']))
                        <div class="text-danger mt-1">MoMo trả lời: {{ $r->gateway_response['message'] }}</div>
                    @endif

                    @if($r->items->isNotEmpty())
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($r->items as $d)
                                <li>
                                    Trả về {{ $d->quantity }} × {{ $d->orderItem->product_name }}
                                    — {{ $d->restock ? 'cộng lại vào kho' : 'không bán lại được, không cộng kho' }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($r->status === \App\Enums\RefundStatus::Pending)
                        <div class="alert alert-warning py-2 px-2 mt-2 mb-0">
                            Không nhận được trả lời từ MoMo. Tìm mã <strong>{{ $r->code }}</strong> trên cổng MoMo:

                            @can('tai-chinh')
                            <form method="POST" action="{{ route('admin.refunds.confirm', $r) }}" class="d-flex flex-wrap gap-2 mt-2">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="reference" class="form-control form-control-sm" style="max-width: 14rem"
                                       placeholder="Mã giao dịch hoàn trên MoMo" required maxlength="100">
                                <button type="submit" class="btn btn-sm btn-outline-admin">Đã hoàn</button>
                            </form>

                            <form method="POST" action="{{ route('admin.refunds.fail', $r) }}" class="mt-2"
                                  onsubmit="return confirm('Ghi {{ $r->code }} là KHÔNG hoàn được? Số tiền này sẽ hoàn lại được lần nữa.');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-ghost">Không có trên MoMo — không thành công</button>
                            </form>
                            @endcan
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($refundBlocked !== null)
        <p class="admin-page-subtitle mb-0">{{ $refundBlocked }}</p>
    @elseif(! auth()->user()?->can('tai-chinh'))
        <p class="admin-page-subtitle mb-0">Ghi hoàn tiền thuộc quyền tài chính.</p>
    @else
        <form method="POST" action="{{ route('admin.orders.refunds.store', $order) }}"
              onsubmit="return confirm('Ghi hoàn tiền cho đơn {{ $order->order_number }}? Khoản hoàn đã ghi không xoá được.');">
            @csrf

            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label small mb-1" for="refund-amount">Số tiền hoàn (₫)</label>
                    <input type="number" id="refund-amount" name="amount" step="1" min="1"
                           max="{{ (int) $conHoan }}"
                           value="{{ old('amount', (int) $conHoan) }}"
                           class="form-control form-control-sm @error('amount') is-invalid @enderror" required>
                    <x-form-error name="amount"/>
                </div>

                <div class="col-12">
                    <label class="form-label small mb-1" for="refund-method">Cách hoàn</label>
                    <select id="refund-method" name="method" class="form-select form-select-sm" required>
                        @foreach($refundMethods as $m)
                            <option value="{{ $m->value }}" @selected(old('method') === $m->value)>{{ $m->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small mb-1" for="refund-reason">Lý do</label>
                    <select id="refund-reason" name="reason" class="form-select form-select-sm" required>
                        @foreach($refundReasons as $l)
                            <option value="{{ $l->value }}" @selected(old('reason') === $l->value)>{{ $l->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small mb-1" for="refund-reference">Mã giao dịch</label>
                    <input type="text" id="refund-reference" name="reference" maxlength="100"
                           value="{{ old('reference') }}" class="form-control form-control-sm"
                           placeholder="Bắt buộc với chuyển khoản">
                </div>

                <div class="col-12">
                    <label class="form-label small mb-1" for="refund-note">Ghi chú</label>
                    <textarea id="refund-note" name="note" rows="2" maxlength="2000" class="form-control form-control-sm"
                              placeholder="Ví dụ: khách gửi ảnh 2 cành hồng dập, hoàn 30%">{{ old('note') }}</textarea>
                </div>
            </div>

            @if($order->status === \App\Enums\OrderStatus::Completed)
                <div class="mt-3">
                    <p class="small fw-bold mb-1">Hàng khách gửi trả về (nếu có)</p>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead>
                                <tr>
                                    <th>Mặt hàng</th>
                                    <th class="text-end">Còn trả được</th>
                                    <th style="width: 6rem">Trả về</th>
                                    <th>Còn bán được</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $quaTraKem = app(\App\Services\Gift\GiftReturnCalculator::class)->bangTraKem($order); @endphp
                                @foreach($order->items as $item)
                                    @php $con = max(0, $returnable[$item->id] ?? 0); @endphp
                                    <tr>
                                        <td>
                                            @if($item->is_gift)
                                                <span class="badge text-bg-success">Quà miễn phí</span>
                                            @endif
                                            {{ $item->product_name }}
                                            @if($item->variant_name)
                                                <span class="text-muted">— {{ $item->variant_name }}</span>
                                            @endif
                                            @if($item->is_gift && isset($quaTraKem[$item->id]))
                                                <span class="d-block text-muted">
                                                    {{ $quaTraKem[$item->id]['quy_tac'] === 'khong_thu_hoi' ? 'Quà không thu hồi' : 'Tự điền theo số món chính trả về — sửa được' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $con }}</td>
                                        <td>
                                            <input type="number" name="items[{{ $item->id }}][quantity]" min="0" max="{{ $con }}" step="1"
                                                   value="{{ old('items.'.$item->id.'.quantity', 0) }}"
                                                   class="form-control form-control-sm" @disabled($con === 0)
                                                   @if($item->is_gift && isset($quaTraKem[$item->id]))
                                                       data-qua-tra-kem="{{ json_encode($quaTraKem[$item->id]) }}"
                                                   @elseif(! $item->is_gift)
                                                       data-dong-tra="{{ $item->id }}"
                                                   @endif
                                                   aria-label="Số lượng {{ $item->product_name }} trả về">
                                        </td>
                                        <td>
                                            <input type="hidden" name="items[{{ $item->id }}][restock]" value="0">
                                            <input type="checkbox" name="items[{{ $item->id }}][restock]" value="1" class="form-check-input"
                                                   @checked(old('items.'.$item->id.'.restock')) @disabled($con === 0)
                                                   aria-label="{{ $item->product_name }} còn bán được, cộng lại vào kho">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <p class="admin-page-subtitle small mt-2 mb-2">
                @if(in_array(\App\Enums\RefundMethod::Momo, $refundMethods, true))
                    Hoàn qua MoMo: hệ thống chuyển tiền về đúng ví khách đã trả, ngay khi bấm.
                @endif
                Chuyển khoản và tiền mặt: chuyển cho khách trước, rồi ghi lại ở đây.
            </p>

            <button type="submit" class="btn btn-primary-brand btn-sm">Ghi hoàn tiền</button>
        </form>
    @endif

</div>
