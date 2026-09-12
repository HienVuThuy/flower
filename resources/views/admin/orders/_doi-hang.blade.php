{{--
    ĐỔI HÀNG của một đơn.
    ============================================================
    Biến cần có: $order (đã nạp items.product, exchanges.items),
    $exchangeBlocked, $exchangeable, $exchangeWhyNot.

    ============================================================
    NÓI RA VÌ SAO KHÔNG ĐỔI ĐƯỢC, thay vì giấu cái biểu mẫu đi.

    Người đứng ở quầy đang có khách trước mặt. "Không thấy nút đâu" bắt
    họ đi hỏi người khác; "đã quá hạn đổi 7 ngày kể từ khi giao (hết hạn
    12/09/2026)" là câu họ đọc thẳng cho khách nghe.
--}}

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<div class="admin-panel mb-3" id="doi-hang">

    <h2 class="h6 fw-bold mb-1">Đổi hàng</h2>

    <p class="admin-page-subtitle mb-3">
        Hạn đổi {{ \App\Services\Exchange\ExchangeService::HAN_DOI_NGAY }} ngày kể từ khi giao.
        Hoa tươi không đổi được. Lỗi cửa hàng thì cửa hàng chịu phí ship, khách đổi ý thì khách trả.
    </p>

    {{-- ===== Phiếu đã có ===== --}}
    @if($order->exchanges->isNotEmpty())
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Phiếu</th>
                        <th scope="col">Lý do</th>
                        <th scope="col">Chênh lệch</th>
                        <th scope="col">Tình trạng</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->exchanges as $p)
                        <tr>
                            <td>
                                <a data-admin-link href="{{ route('admin.exchanges.show', $p) }}">{{ $p->code }}</a>
                                <span class="d-block admin-page-subtitle small">
                                    <x-site.time :at="$p->created_at" format="d/m/Y H:i" />
                                </span>
                            </td>
                            <td>{{ $p->reason->label() }}</td>
                            <td>
                                @if(bccomp((string) $p->chenh_lech, '0', 2) === 0)
                                    <span class="admin-page-subtitle">đổi ngang</span>
                                @elseif($p->cuaHangNoLai())
                                    cửa hàng trả lại {{ $tien(abs((float) $p->chenh_lech)) }}
                                @else
                                    khách bù {{ $tien($p->chenh_lech) }}
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $p->status->tone() }}">{{ $p->status->label() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ===== Lập phiếu mới ===== --}}
    @if($exchangeBlocked)
        <p class="analytics-empty mb-0">{{ $exchangeBlocked }}</p>
    @else
        <form method="POST" action="{{ route('admin.exchanges.store', $order) }}">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="doi-reason">Lý do đổi</label>
                <select id="doi-reason" name="reason" class="form-select form-select-sm w-auto" required>
                    @foreach(\App\Enums\ExchangeReason::cases() as $ly)
                        <option value="{{ $ly->value }}" @selected(old('reason') === $ly->value)>
                            {{ $ly->label() }} — {{ $ly->hint() }}
                        </option>
                    @endforeach
                </select>
                <x-form-error name="reason"/>
            </div>

            <h3 class="admin-section-title">Khách trả về</h3>

            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Món</th>
                            <th scope="col">Giá đã trả</th>
                            <th scope="col">Còn đổi được</th>
                            <th scope="col">Đổi bao nhiêu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    {{ $item->product_name }}
                                    @if($item->variant_name)
                                        <span class="admin-page-subtitle">— {{ $item->variant_name }}</span>
                                    @endif
                                </td>
                                <td>{{ $tien($item->unit_price) }}</td>
                                <td>
                                    {{-- Vì sao món này không đổi được, nói ngay tại dòng đó. --}}
                                    @if($exchangeWhyNot[$item->id])
                                        <span class="admin-page-subtitle">{{ $exchangeWhyNot[$item->id] }}</span>
                                    @else
                                        {{ $exchangeable[$item->id] }} / {{ $item->quantity }}
                                    @endif
                                </td>
                                <td>
                                    @if($exchangeWhyNot[$item->id])
                                        <span class="admin-page-subtitle">—</span>
                                    @else
                                        <label class="visually-hidden" for="tra-{{ $item->id }}">
                                            Số lượng đổi của {{ $item->product_name }}
                                        </label>
                                        <input type="number" id="tra-{{ $item->id }}"
                                               name="tra[{{ $item->id }}][quantity]"
                                               class="form-control form-control-sm"
                                               style="max-width:6rem"
                                               min="0" max="{{ $exchangeable[$item->id] }}"
                                               value="{{ old('tra.' . $item->id . '.quantity', 0) }}">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="admin-section-title">Gửi cho khách</h3>

            {{--
                BA DÒNG CỐ ĐỊNH, không thêm bớt bằng JavaScript.

                Một lần đổi thực tế là một hoặc hai món. Ba ô trống đủ cho
                gần hết trường hợp, và không cần script nào — nên nó còn
                chạy khi script hỏng.
            --}}
            <div class="mb-3">
                @for($i = 0; $i < 3; $i++)
                    <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
                        <div>
                            <label class="form-label small mb-1" for="moi-{{ $i }}-sp">Sản phẩm</label>
                            <select id="moi-{{ $i }}-sp" name="moi[{{ $i }}][product_id]"
                                    class="form-select form-select-sm">
                                <option value="">— không chọn —</option>
                                @foreach($hangDoiDuoc as $sp)
                                    <option value="{{ $sp->id }}"
                                            @selected(old('moi.' . $i . '.product_id') == $sp->id)>
                                        {{ $sp->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label small mb-1" for="moi-{{ $i }}-sl">Số lượng</label>
                            <input type="number" id="moi-{{ $i }}-sl" name="moi[{{ $i }}][quantity]"
                                   class="form-control form-control-sm" style="max-width:6rem"
                                   min="0" value="{{ old('moi.' . $i . '.quantity', 0) }}">
                        </div>
                    </div>
                @endfor

                <p class="admin-page-subtitle small mb-0">
                    Danh sách chỉ có hàng đổi được — hoa tươi không xuất hiện ở đây.
                    Giá lấy theo giá đang bán hôm nay, kể cả khuyến mại đang chạy.
                </p>
            </div>

            <div class="mb-3">
                <label class="form-label" for="doi-note">Ghi chú</label>
                <textarea id="doi-note" name="note" rows="2" class="form-control form-control-sm"
                          maxlength="1000">{{ old('note') }}</textarea>
                <x-form-error name="note"/>
            </div>

            <button type="submit" class="btn btn-sm btn-primary-brand">Lập phiếu đổi</button>
        </form>
    @endif

</div>
