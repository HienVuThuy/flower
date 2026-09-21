{{-- ---------- Điểm thưởng ---------- --}}
<div class="surface-card p-4">

    <h2 class="text-h4 mb-1">Điểm thưởng</h2>
    <p class="text-caption mb-3">
        Tích điểm khi bài Góc cây của bạn được duyệt và khi ghé cửa hàng nhiều ngày liền. Đổi điểm lấy voucher dùng cho đơn sau.
    </p>

    <p class="points-balance mb-4" data-so-du="{{ $diem['so_du'] }}">
        <span class="points-balance__so">{{ number_format($diem['so_du'], 0, ',', '.') }}</span>
        <span class="text-caption">điểm</span>
    </p>

    @php $mocTiep = \App\Services\Points\VisitStreak::mocTiepTheo($diem['chuoi']); @endphp
    <div class="points-offer mb-4" data-chuoi="{{ $diem['chuoi'] }}">
        @if($diem['chuoi'] > 0)
            <p class="points-offer__giam mb-1">Chuỗi {{ $diem['chuoi'] }} ngày ghé thăm</p>
            <p class="text-caption mb-0">
                Ghé lại ngày mai để giữ chuỗi. Còn {{ $mocTiep['ngay'] - $diem['chuoi'] }} ngày nữa tới mốc {{ $mocTiep['ngay'] }} ngày: +{{ $mocTiep['diem'] }} điểm.
            </p>
        @else
            <p class="points-offer__giam mb-1">Chuỗi ngày ghé thăm</p>
            <p class="text-caption mb-0">Ghé cửa hàng {{ $mocTiep['ngay'] }} ngày liền để nhận +{{ $mocTiep['diem'] }} điểm.</p>
        @endif
    </div>

    <h3 class="text-h5 mb-2">Đổi voucher</h3>
    <div class="row g-3 mb-4">
        @foreach($diem['goi'] as $ma => $goi)
            @php $du = $diem['so_du'] >= $goi['diem']; @endphp
            <div class="col-sm-6">
                <div class="points-offer {{ $du ? '' : 'is-locked' }}" data-goi="{{ $ma }}">
                    <p class="points-offer__giam mb-1">Giảm {{ \App\Services\Shop\Money::format($goi['giam']) }}</p>
                    <p class="text-caption mb-2">
                        Đơn từ {{ \App\Services\Shop\Money::format($goi['don_toi_thieu']) }} · dùng trong {{ $goi['ngay'] }} ngày
                    </p>
                    <form method="POST" action="{{ route('shop.points.redeem') }}">
                        @csrf
                        <input type="hidden" name="goi" value="{{ $ma }}">
                        <button type="submit" class="btn btn-primary-brand w-100" @disabled(! $du)>
                            Đổi {{ number_format($goi['diem'], 0, ',', '.') }} điểm
                        </button>
                    </form>
                    @unless($du)
                        <p class="text-caption mt-2 mb-0">Còn thiếu {{ number_format($goi['diem'] - $diem['so_du'], 0, ',', '.') }} điểm</p>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>

    @if($diem['so_du'] < 0)
        <p class="text-caption mb-4" data-so-du-am>
            Số dư đang âm vì một đơn được hoàn tiền sau khi điểm của đơn đó đã được dùng. Tích thêm điểm để đổi được ưu đãi.
        </p>
    @endif

    @php
        $kiem = \App\Services\Points\PointEarning::class;
        $baiViet = \App\Services\Points\CommunityReward::class;
    @endphp
    <h3 class="text-h5 mb-2">Cách kiếm điểm</h3>
    <ul class="points-history list-unstyled mb-4" data-cach-kiem-diem>
        <li class="points-history__row"><span>Mua hàng (tính khi đơn đã giao, không gồm phí vận chuyển)</span><strong>1 điểm / {{ number_format($kiem::dongMoiDiem(), 0, ',', '.') }}đ</strong></li>
        <li class="points-history__row"><span>Đánh giá sản phẩm đã mua, có nhận xét từ {{ $kiem::NHAN_XET_TOI_THIEU }} ký tự</span><strong>+{{ $kiem::danhGiaNhanXet() }}</strong></li>
        <li class="points-history__row"><span>Đánh giá chỉ chấm sao</span><strong>+{{ $kiem::danhGiaChiSao() }}</strong></li>
        <li class="points-history__row"><span>Bài Góc cây được duyệt (có ảnh +{{ $baiViet::CO_ANH }}, nổi bật +{{ $baiViet::NOI_BAT }})</span><strong>+{{ $baiViet::CO_BAN }}</strong></li>
        <li class="points-history__row"><span>Người khác thích bài Góc cây của bạn (tối đa {{ $baiViet::THICH_TOI_DA_MOI_TUAN }} điểm mỗi tuần)</span><strong>+{{ $baiViet::LUOT_THICH }}</strong></li>
        <li class="points-history__row"><span>Chuỗi ngày ghé thăm: ngày thứ 3 / mỗi 7 ngày</span><strong>+{{ \App\Services\Points\VisitStreak::moc(3) }} / +{{ \App\Services\Points\VisitStreak::moc(7) }}</strong></li>
    </ul>

    <h3 class="text-h5 mb-2">Lịch sử</h3>
    @if($diem['lich_su']->isEmpty())
        <p class="text-caption mb-0">Chưa có điểm nào. Đăng một bài ở <a href="{{ route('shop.community.index') }}">Góc cây của bạn</a> để bắt đầu.</p>
    @else
        <ul class="points-history list-unstyled mb-0">
            @foreach($diem['lich_su'] as $dong)
                <li class="points-history__row">
                    <span>
                        {{ $dong->reason->label() }}
                        @if($dong->note)
                            <span class="d-block text-caption">{{ $dong->note }}</span>
                        @endif
                        <span class="d-block text-caption"><x-site.time :at="$dong->created_at" format="d/m/Y" /></span>
                    </span>
                    <strong class="{{ $dong->amount < 0 ? 'points-history__tru' : 'points-history__cong' }}">
                        {{ $dong->amount > 0 ? '+' : '−' }}{{ number_format(abs($dong->amount), 0, ',', '.') }}
                    </strong>
                </li>
            @endforeach
        </ul>
    @endif

</div>
