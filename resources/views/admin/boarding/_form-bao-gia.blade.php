{{-- BÁO GIÁ / XÁC NHẬN phiếu đang chờ. Một form, hai nút:
     "Gửi báo giá" → khách xem từng dòng, xác nhận / yêu cầu sửa / huỷ;
     "Xác nhận thẳng" → chỉ khi phiếu không bắt buộc báo giá (cây thường, không yêu cầu riêng). --}}
@php
    use App\Enums\BoardingExtraStatus;
    use App\Enums\BoardingHandover;
    use App\Enums\BoardingMode;
    use App\Services\Shop\Money;

    $lyDo = $phieu->lyDoPhaiBaoGia();
    $choBaoGia = $phieu->extras->where('status', BoardingExtraStatus::ChoBaoGia);
@endphp

<form method="POST" action="{{ route('admin.boarding.confirm', $phieu) }}" class="admin-panel p-4 mb-3" data-xac-nhan-cham-ho>
    @csrf @method('PATCH')
    <h2 class="h6 fw-bold mb-1">Báo giá cho khách</h2>

    @if($lyDo)
        <div class="alert alert-warning small py-2 mb-3" data-phai-bao-gia>
            Phiếu này phải gửi báo giá để khách xác nhận:
            <ul class="mb-0 ps-3">@foreach($lyDo as $l)<li>{{ $l }}</li>@endforeach</ul>
        </div>
    @endif

    @if($phieu->declared_value !== null)
        <p class="small mb-2">Khách khai giá trị cây: <strong>{{ Money::format((string) $phieu->declared_value) }}</strong></p>
    @endif

    <div class="row g-2 mb-2">
        <div class="col-6">
            <label class="form-label small" for="bg-gui">Ngày nhận cây</label>
            <input id="bg-gui" type="date" name="drop_off_on" required class="form-control" value="{{ old('drop_off_on', $phieu->drop_off_on->toDateString()) }}">
            <x-form-error name="drop_off_on" />
        </div>
        @if($phieu->mode !== BoardingMode::KhongHen)
            <div class="col-6">
                <label class="form-label small" for="bg-tra">Ngày trả cây</label>
                <input id="bg-tra" type="date" name="return_on" class="form-control" value="{{ old('return_on', $phieu->return_on?->toDateString()) }}">
                <x-form-error name="return_on" />
            </div>
        @endif
        <div class="col-6">
            <label class="form-label small" for="xn-thang">Giá chốt / tháng</label>
            <input id="xn-thang" type="number" name="monthly_price" required min="1000" step="1000" class="form-control @error('monthly_price') is-invalid @enderror"
                   value="{{ old('monthly_price', (int) $phieu->monthly_price) }}">
            <x-form-error name="monthly_price" />
        </div>
        <div class="col-6">
            <label class="form-label small" for="xn-nam">Giá / năm</label>
            <input id="xn-nam" type="number" name="yearly_price" min="1000" step="1000" class="form-control" value="{{ old('yearly_price', (int) $phieu->yearly_price) }}" placeholder="12 tháng">
        </div>
        @if($phieu->handover === BoardingHandover::CuaHangLay)
            <div class="col-6">
                <label class="form-label small" for="xn-phi">Phí đến lấy + trả cây</label>
                <input id="xn-phi" type="number" name="handover_fee" min="0" step="1000" class="form-control" value="{{ old('handover_fee', (int) $phieu->handover_fee) }}">
            </div>
        @endif
        <div class="col-6">
            <label class="form-label small" for="xn-dc">Điều chỉnh một lần (âm là giảm)</label>
            <input id="xn-dc" type="number" name="adjustment" step="1000" class="form-control" value="{{ old('adjustment', (int) $phieu->adjustment) }}">
        </div>
        <div class="col-12">
            <input type="text" name="adjustment_reason" maxlength="255" class="form-control @error('adjustment_reason') is-invalid @enderror"
                   placeholder="Lý do điều chỉnh (cây to, chậu nặng, khách quen…)" value="{{ old('adjustment_reason', $phieu->adjustment_reason) }}" aria-label="Lý do điều chỉnh">
            <x-form-error name="adjustment_reason" />
        </div>
    </div>

    @if($choBaoGia->isNotEmpty())
        <p class="fw-semibold small mb-1 mt-3">Yêu cầu riêng của khách — báo giá từng việc</p>
        @foreach($choBaoGia as $x)
            <div class="border rounded p-2 mb-2" data-bao-gia-viec="{{ $x->id }}">
                <div class="small fw-semibold">{{ $x->title }}</div>
                @if($x->customer_note)<div class="small admin-page-subtitle">{{ $x->customer_note }}</div>@endif
                <div class="d-flex flex-wrap gap-2 mt-1 align-items-center">
                    <input type="number" name="extras[{{ $x->id }}][gia]" min="0" step="1000" class="form-control form-control-sm" style="max-width: 9rem"
                           placeholder="Giá" value="{{ old("extras.{$x->id}.gia", $x->price !== null ? (int) $x->price : '') }}" aria-label="Giá {{ $x->title }}">
                    <input type="text" name="extras[{{ $x->id }}][ghi_chu]" maxlength="500" class="form-control form-control-sm" style="flex: 1 1 8rem"
                           placeholder="Gồm những gì" aria-label="Ghi chú">
                    <label class="small d-flex align-items-center gap-1 mb-0">
                        <input type="checkbox" class="form-check-input" name="extras[{{ $x->id }}][khong_nhan]" value="1"> Không nhận
                    </label>
                    <input type="text" name="extras[{{ $x->id }}][ly_do]" maxlength="500" class="form-control form-control-sm" style="flex: 1 1 8rem"
                           placeholder="Lý do nếu không nhận" aria-label="Lý do không nhận">
                </div>
                <x-form-error :name="'extras.' . $x->id . '.gia'" />
                <x-form-error :name="'extras.' . $x->id . '.ly_do'" />
            </div>
        @endforeach
    @endif

    <p class="fw-semibold small mb-1 mt-3">Cửa hàng thêm việc cần làm (không bắt buộc)</p>
    @for($i = 0; $i < 3; $i++)
        <div class="d-flex gap-2 mb-1">
            <input type="text" name="them[{{ $i }}][viec]" maxlength="200" class="form-control form-control-sm" placeholder="Ví dụ: thay đất, xử lý nấm" aria-label="Việc thêm {{ $i + 1 }}" value="{{ old("them.$i.viec") }}">
            <input type="number" name="them[{{ $i }}][gia]" min="0" step="1000" class="form-control form-control-sm" style="max-width: 9rem" placeholder="Giá" aria-label="Giá việc thêm {{ $i + 1 }}" value="{{ old("them.$i.gia") }}">
        </div>
        <x-form-error :name="'them.' . $i . '.gia'" />
    @endfor

    <textarea name="note" rows="2" maxlength="1000" class="form-control mt-2 mb-3" placeholder="Lời nhắn kèm báo giá (giải thích giá, cách chăm…)" aria-label="Lời nhắn kèm báo giá">{{ old('note') }}</textarea>
    <x-form-error name="cach" />

    <div class="d-grid gap-2">
        <button type="submit" name="cach" value="bao_gia" class="btn btn-primary-brand">Gửi báo giá cho khách xác nhận</button>
        <button type="submit" name="cach" value="xac_nhan" class="btn btn-outline-admin" @disabled($lyDo !== [])
                title="{{ $lyDo ? 'Phiếu này phải báo giá' : 'Cây thường, giá theo bảng — xác nhận luôn' }}">Xác nhận thẳng, không cần báo giá</button>
    </div>
</form>
