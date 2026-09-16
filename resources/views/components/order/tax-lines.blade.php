@props([
    'total' => null,
    'rows' => [],
])

{{-- THUẾ GTGT — DÒNG THÔNG TIN, KHÔNG PHẢI DÒNG CỘNG --}}

@php
    $co = $total !== null && bccomp((string) $total, '0', 2) > 0;
    $mucChu = fn (?string $r) => \App\Services\Tax\TaxCalculator::formatRate($r);
    $tien = fn (string $v) => \App\Services\Shop\Money::format($v);
@endphp

@if($co)
    <div class="order-summary__tax">
        <span class="order-summary__tax-label">Trong đó thuế GTGT</span>
        <span class="order-summary__tax-value">{{ $tien((string) $total) }}</span>

        @if(count($rows) > 1)
            <ul class="order-summary__tax-rates">
                @foreach($rows as $muc)
                    <li>
                        <span>
                            @if($muc['rate'] === null)
                                {{ $mucChu(null) }}
                            @else
                                VAT {{ $mucChu($muc['rate']) }}
                            @endif
                            trên {{ $tien($muc['net']) }}
                        </span>
                        <span>{{ $tien($muc['tax']) }}</span>
                    </li>
                @endforeach
            </ul>
        @elseif(count($rows) === 1 && $rows[0]['rate'] !== null)
            <span class="order-summary__tax-note">thuế suất {{ $mucChu($rows[0]['rate']) }}</span>
        @endif
    </div>
@endif
