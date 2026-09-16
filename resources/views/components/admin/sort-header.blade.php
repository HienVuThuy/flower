@props([
    'khoa',

    'nhan',

    'dau' => 'tang',
])

@php
    $dangSap = request('sap') === $khoa;
    $huongHienTai = request('huong') === 'giam' ? 'giam' : 'tang';

    $huongTiepTheo = $dangSap
        ? ($huongHienTai === 'giam' ? 'tang' : 'giam')
        : $dau;

    $duongDan = request()->fullUrlWithQuery([
        'sap' => $khoa,
        'huong' => $huongTiepTheo,
        'page' => null,
    ]);
@endphp

<th @if($dangSap) aria-sort="{{ $huongHienTai === 'giam' ? 'descending' : 'ascending' }}" @endif
    {{ $attributes }}>
    <a href="{{ $duongDan }}" class="admin-sort {{ $dangSap ? 'is-active' : '' }}">
        <span>{{ $nhan }}</span>

        @if($dangSap)
            <x-site.icon
                name="chevron-down"
                class="admin-sort__icon {{ $huongHienTai === 'tang' ? 'is-up' : '' }}" />
        @endif
    </a>
</th>
