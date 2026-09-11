@extends('layouts.admin')

@section('title', 'Lập phiếu nhập')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Lập phiếu nhập kho</h1>
    <p class="admin-page-subtitle">
        {{--
            NÓI TRƯỚC ĐIỀU SẼ XẢY RA.

            Tạo phiếu KHÔNG cộng vào kho — còn một bước ghi sổ nữa. Không
            nói ra thì người lập bấm "Lưu", đi kiểm kho, thấy số không
            đổi, và tưởng hệ thống hỏng.
        --}}
        Lưu xong phiếu vẫn là <strong>nháp</strong> — tồn kho chưa đổi.
        Kiểm lại rồi bấm "Ghi sổ" thì kho mới được cộng thêm.
    </p>
</div>

<form method="POST" action="{{ route('admin.stock-receipts.store') }}">
    @csrf

    <div class="row g-3">

        <div class="col-lg-8">
            <div class="admin-panel p-4">

                <h2 class="h6 fw-bold mb-3">Hàng nhập</h2>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-receipt-lines>
                        <thead>
                            <tr>
                                <th style="min-width: 16rem;">Mặt hàng</th>
                                <th style="width: 7rem;">Số lượng</th>
                                <th style="width: 10rem;">Giá vốn / đơn vị <span class="fw-normal text-muted">(chưa VAT)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{--
                                DỰNG SẴN 8 DÒNG TRỐNG.

                                Không có JavaScript thì vẫn nhập được cả
                                phiếu; nút "Thêm dòng" bên dưới chỉ là
                                phần thêm. Dòng để trống bị bỏ qua im
                                lặng — controller lọc.
                            --}}
                            @for($i = 0; $i < 8; $i++)
                                <tr data-receipt-line>
                                    <td>
                                        <select name="items[{{ $i }}][mat_hang]" class="form-select form-select-sm">
                                            <option value="">— chọn mặt hàng —</option>
                                            @foreach($donViKho as $dv)
                                                <option value="{{ $dv['value'] }}"
                                                    @selected($i === 0 && $chonSan === $dv['value'])>
                                                    {{ $dv['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <input type="number" name="items[{{ $i }}][quantity]"
                                               class="form-control form-control-sm" step="1" placeholder="0">
                                    </td>

                                    <td>
                                        <input type="number" name="items[{{ $i }}][unit_cost]"
                                               class="form-control form-control-sm" step="1000" min="0"
                                               placeholder="để trống nếu chưa biết">
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-outline-admin btn-sm" data-receipt-add>
                        <x-site.icon name="plus" /> Thêm dòng
                    </button>
                </div>

                <x-form-error name="items" />

                {{--
                    HAI ĐIỀU DỄ HIỂU NHẦM, nói ngay cạnh ô nhập.
                --}}
                <p class="admin-page-subtitle mt-3 mb-0">
                    <strong>Giá vốn</strong> là giá mua <strong>chưa gồm VAT đầu vào</strong> (theo hoá đơn của nhà cung cấp) — trang Lãi gộp so nó với doanh thu đã trừ VAT. Để trống nghĩa là <em>chưa biết</em> (hàng tặng, hàng mẫu),
                    khác với 0₫. Tổng tiền sẽ bỏ qua những dòng đó thay vì tính bằng không.
                    <br>
                    <strong>Số lượng âm</strong> dùng để lập phiếu điều chỉnh khi nhập nhầm —
                    phiếu đã ghi sổ không sửa được.
                </p>

            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel p-4">

                <h2 class="h6 fw-bold mb-3">Thông tin phiếu</h2>

                <div class="mb-3">
                    <label class="form-label">Mã phiếu</label>
                    {{-- Chỉ để xem: mã thật do máy chủ sinh lúc lưu, vì
                         giữa lúc mở trang và lúc bấm Lưu có thể đã có
                         người khác dùng mất mã này. --}}
                    <input type="text" class="form-control" value="{{ $ma }}" disabled>
                    <div class="form-text">Máy chủ sinh lại mã khi lưu.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="received_at">Ngày nhập</label>
                    <input type="date" id="received_at" name="received_at"
                           class="form-control @error('received_at') is-invalid @enderror"
                           value="{{ old('received_at', now()->toDateString()) }}"
                           max="{{ now()->toDateString() }}">
                    {{-- Ngày trong quá khứ HỢP LỆ: hàng về thứ Bảy, thứ
                         Hai mới ngồi nhập máy. --}}
                    <div class="form-text">Ngày hàng thật sự về, không phải ngày ngồi nhập máy.</div>
                    <x-form-error name="received_at" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="supplier">Nhà cung cấp</label>
                    <input type="text" id="supplier" name="supplier" maxlength="200"
                           class="form-control @error('supplier') is-invalid @enderror"
                           value="{{ old('supplier') }}" placeholder="Vườn Đà Lạt, chợ Quảng Bá…">
                    <x-form-error name="supplier" />
                </div>

                <div class="mb-4">
                    <label class="form-label" for="note">Ghi chú</label>
                    <textarea id="note" name="note" rows="3" maxlength="500"
                              class="form-control @error('note') is-invalid @enderror"
                              placeholder="Số hoá đơn, tình trạng hàng…">{{ old('note') }}</textarea>
                    <x-form-error name="note" />
                </div>

                <button type="submit" class="btn btn-primary-brand w-100">Lưu phiếu nháp</button>

                <a data-admin-link href="{{ route('admin.stock-receipts.index') }}"
                   class="btn btn-ghost w-100 mt-2">Huỷ</a>

            </div>
        </div>

    </div>

</form>

@endsection
