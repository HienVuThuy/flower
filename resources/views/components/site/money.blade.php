@props(['amount', 'symbol' => true])

{{ $symbol
    ? \App\Services\Shop\Money::format($amount)
    : \App\Services\Shop\Money::number($amount) }}
