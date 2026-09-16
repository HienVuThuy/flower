@props(['ky', 'periods', 'route'])

{{-- Ô chọn kỳ: mấy mốc dựng sẵn, cộng một khoảng ngày tự chọn. --}}
<div class="chon-ky-boc">
<div class="chon-ky">

    <div class="chon-ky__moc">
        @foreach($periods as $value => $label)
            <a data-admin-link href="{{ route($route, ['ky' => $value]) }}"
               class="btn btn-sm {{ ! $ky->laTuyChon() && $ky->ma === (string) $value ? 'btn-primary-brand' : 'btn-outline-admin' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route($route) }}" data-chon-ky
          @class(['chon-ky__khoang', 'is-active' => $ky->laTuyChon()])>
        <input type="hidden" name="ky" value="{{ \App\Services\Analytics\ChonKy::TUY_CHON }}">

        <label class="chon-ky__nhan" for="ky-tu">Từ</label>
        <input type="date" id="ky-tu" name="tu" class="form-control form-control-sm"
               value="{{ $ky->oTu() }}"
               max="{{ \App\Services\Time\Gio::choONgay(now()) }}"
               data-chon-ky-o>

        <label class="chon-ky__nhan" for="ky-den">đến</label>
        <input type="date" id="ky-den" name="den" class="form-control form-control-sm"
               value="{{ $ky->oDen() }}"
               max="{{ \App\Services\Time\Gio::choONgay(now()) }}"
               data-chon-ky-o>

        <button type="submit" class="btn btn-sm {{ $ky->laTuyChon() ? 'btn-primary-brand' : 'btn-outline-admin' }} chon-ky__xem">
            Xem
        </button>
    </form>


</div>

    @if($ky->khoangHienThi())
        <span class="chon-ky__khoang-chu">{{ $ky->khoangHienThi() }}</span>
    @endif

</div>
