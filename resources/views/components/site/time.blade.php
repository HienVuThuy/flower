@props(['at', 'format' => 'H:i d/m/Y', 'relative' => false])

@php $moc = \App\Services\Time\Gio::hien($at); @endphp

{{-- Hiện một mốc thời gian THEO GIỜ NGƯỜI ĐỌC. --}}
@if($moc)
    <time datetime="{{ $moc->toIso8601String() }}" {{ $attributes }}>{{
        $relative ? $moc->diffForHumans() : $moc->format($format)
    }}</time>
@else
    {{ $slot }}
@endif
