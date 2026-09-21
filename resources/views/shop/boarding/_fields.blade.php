{{-- CÁC Ô CỦA PHIẾU CHĂM HỘ — dùng chung cho phiếu khách gửi online và phiếu cửa hàng lập tại quầy,
     cùng thứ tự với phiếu in (print.blade.php) để chép từ phiếu giấy sang không lệch ô. --}}
@php
    use App\Enums\BoardingHandover;
    use App\Enums\BoardingMode;
    use App\Services\Shop\Money;
@endphp

<div class="mb-3">
    <label class="form-label" for="ch-rate">Loại cây</label>
    <select id="ch-rate" name="boarding_rate_id" class="form-select @error('boarding_rate_id') is-invalid @enderror" data-bao-gia>
        @foreach($cacGia as $gia)
            <option value="{{ $gia->id }}" @selected((string) $rateChon === (string) $gia->id)>
                {{ $gia->name }} — {{ Money::format($gia->monthly_price) }}/tháng
            </option>
        @endforeach
    </select>
    <x-form-error name="boarding_rate_id" />
</div>

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <label class="form-label" for="ch-ten">Cây gửi chăm</label>
        <input id="ch-ten" name="plant_name" maxlength="150" required class="form-control @error('plant_name') is-invalid @enderror"
               value="{{ old('plant_name', $tenCayMacDinh ?? '') }}" placeholder="Ví dụ: Đào thế chậu cao 1,2m">
        <x-form-error name="plant_name" />
    </div>
    <div class="col-md-5">
        <label class="form-label" for="ch-anh">Ảnh cây <span class="text-caption">(không bắt buộc)</span></label>
        <input id="ch-anh" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror">
        <x-form-error name="photo" />
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="ch-note">Tình trạng, lưu ý</label>
    <textarea id="ch-note" name="plant_note" maxlength="500" rows="2" class="form-control"
              placeholder="Cây đang ra lộc, hay rụng lá…">{{ old('plant_note') }}</textarea>
</div>

<fieldset class="mb-3">
    <legend class="form-label">Gửi trong bao lâu?</legend>
    <div class="boarding-modes">
        @foreach(BoardingMode::cases() as $m)
            @continue($m === BoardingMode::TheoDip && $cacDip->isEmpty())
            <label class="boarding-modes__item">
                <input type="radio" class="form-check-input" name="mode" value="{{ $m->value }}" data-bao-gia @checked($cheDo === $m->value)>
                <span><strong>{{ $m->label() }}</strong><span class="d-block text-caption">{{ $m->hint() }}</span></span>
            </label>
        @endforeach
    </div>
    <x-form-error name="mode" />
</fieldset>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="ch-gui">Ngày gửi cây</label>
        <input id="ch-gui" type="date" name="drop_off_on" required min="{{ $homNay->toDateString() }}" data-bao-gia
               class="form-control @error('drop_off_on') is-invalid @enderror" value="{{ old('drop_off_on', ($ngayGuiMacDinh ?? $homNay->addDays(1))->toDateString()) }}">
        <x-form-error name="drop_off_on" />
    </div>

    <div class="col-md-6" data-che-do="thang">
        <label class="form-label" for="ch-thang">Số tháng</label>
        <input id="ch-thang" type="number" name="months" min="1" max="60" data-bao-gia
               class="form-control @error('months') is-invalid @enderror" value="{{ old('months', 1) }}">
        <x-form-error name="months" />
    </div>

    <div class="col-md-6" data-che-do="nam">
        <label class="form-label" for="ch-nam">Số năm</label>
        <input id="ch-nam" type="number" name="years" min="1" max="5" data-bao-gia
               class="form-control @error('years') is-invalid @enderror" value="{{ old('years', 1) }}">
        <x-form-error name="years" />
    </div>

    <div class="col-md-6" data-che-do="den_ngay">
        <label class="form-label" for="ch-tra">Ngày nhận cây lại</label>
        <input id="ch-tra" type="date" name="return_on" data-bao-gia min="{{ $homNay->addDays(2)->toDateString() }}"
               class="form-control @error('return_on') is-invalid @enderror" value="{{ old('return_on') }}">
        <x-form-error name="return_on" />
    </div>

    @if($cacDip->isNotEmpty())
        <div class="col-md-6" data-che-do="theo_dip">
            <label class="form-label" for="ch-dip">Nhận lại trước dịp</label>
            <select id="ch-dip" name="boarding_window_id" data-bao-gia class="form-select @error('boarding_window_id') is-invalid @enderror">
                @foreach($cacDip as $dip)
                    <option value="{{ $dip->id }}" @selected((string) old('boarding_window_id') === (string) $dip->id)>
                        {{ $dip->name }} — cây về ngày {{ $dip->return_on->format('d/m/Y') }}
                    </option>
                @endforeach
            </select>
            <x-form-error name="boarding_window_id" />
            <label class="d-flex gap-2 mt-2">
                <input type="checkbox" class="form-check-input" name="repeat_yearly" value="1" @checked(old('repeat_yearly'))>
                <span class="text-caption">Qua dịp, cửa hàng nhận cây lại chăm tiếp và mang về cho tôi dịp này năm sau.</span>
            </label>
        </div>
    @endif
</div>

<fieldset class="mb-3">
    <legend class="form-label">Giao nhận cây</legend>
    @foreach(BoardingHandover::cases() as $h)
        <label class="d-flex gap-2 mb-1">
            <input type="radio" class="form-check-input" name="handover" value="{{ $h->value }}"
                   @checked(old('handover', BoardingHandover::TuMang->value) === $h->value)>
            <span>{{ $h->label() }}</span>
        </label>
    @endforeach
</fieldset>

<div class="row g-3 mb-3">
    <div class="col-md-5">
        <label class="form-label" for="ch-sdt">Số điện thoại</label>
        <input id="ch-sdt" name="contact_phone" maxlength="20" required inputmode="tel"
               class="form-control @error('contact_phone') is-invalid @enderror" value="{{ old('contact_phone', $sdtMacDinh ?? '') }}">
        <x-form-error name="contact_phone" />
    </div>
    <div class="col-md-7">
        <label class="form-label" for="ch-dc">Địa chỉ <span class="text-caption">(khi cửa hàng đến lấy)</span></label>
        <input id="ch-dc" name="address" maxlength="255" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}">
        <x-form-error name="address" />
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="ch-loi-nhan">Lời nhắn</label>
    <input id="ch-loi-nhan" name="customer_note" maxlength="500" class="form-control" value="{{ old('customer_note') }}">
</div>

<div class="boarding-quote" data-bao-gia-ket-qua aria-live="polite" hidden>
    <span class="text-caption" data-bao-gia-nhan>Tạm tính theo giá tham khảo — cửa hàng xem cây rồi chốt giá</span>
    <strong class="boarding-quote__so" data-bao-gia-so></strong>
    <span class="text-caption" data-bao-gia-chi-tiet></span>
</div>
