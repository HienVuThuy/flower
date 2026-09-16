@extends('layouts.admin')

@section('title', 'Tồn đầu kỳ')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Khai tồn đầu kỳ</h1>
    <p class="admin-page-subtitle">
        Hàng đã nằm trên kệ từ trước khi dùng hệ thống. Khai giá vốn cho chúng
        thì trang Lãi gộp mới tính được — không có bước này, hàng cũ là vùng tối vĩnh viễn.
    </p>
</div>

<x-admin.nhom-tab ten="nhap-kho" />

<div class="admin-panel p-4 mb-3">
    <h2 class="h6 fw-bold mb-2">Hai điều cần biết trước khi khai</h2>
    <ul class="mb-0 ps-3 admin-page-subtitle">
        <li>
            <strong>Phiếu này KHÔNG cộng vào tồn kho.</strong>
            Hàng đã có sẵn trên kệ rồi; ở đây chỉ khai <em>giá vốn</em> cho số đang có.
            Số lượng điền sẵn đúng bằng tồn hiện tại.
        </li>
        <li>
            <strong>Hoa tươi không khai ở đây.</strong>
            Giá vốn hoa đến từ <a data-admin-link href="{{ route('admin.flower-lots.index') }}">lô hoa</a>.
            Khai cả hai chỗ là giá vốn bị đếm hai lần.
        </li>
        <li>
            <strong>Không nhớ giá thì để trống.</strong>
            Món để trống vẫn nằm ngoài phần tính lãi, và trang Lãi gộp có đếm và nói ra
            phần nằm ngoài đó. Bịa một con số cho đủ thì biến “chưa biết” thành
            “biết sai”, và về sau không ai phân biệt được nữa.
        </li>
    </ul>
</div>

@if($daKhai->isNotEmpty())
    <div class="admin-panel p-4 mb-3">
        <h2 class="h6 fw-bold mb-2">Đã khai trước đó</h2>
        <ul class="mb-0 ps-3 admin-page-subtitle">
            @foreach($daKhai as $p)
                <li>
                    <a data-admin-link href="{{ route('admin.stock-receipts.show', $p) }}">{{ $p->code }}</a>
                    — {{ $p->items_count }} mặt hàng,
                    ngày chốt <x-site.time :at="$p->received_at" format="d/m/Y" />,
                    {{ $p->isPosted() ? 'đã ghi sổ' : 'còn nháp' }}
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if($matHang->isEmpty())
    <div class="admin-panel p-4">
        <p class="analytics-empty mb-0">
            Không còn mặt hàng nào vừa có tồn vừa chưa có giá vốn. Không cần khai gì thêm.
        </p>
    </div>
@else
    <form method="POST" action="{{ route('admin.opening-stock.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="admin-panel p-4">

                    <p class="admin-page-subtitle">
                        {{ $matHang->count() }} mặt hàng đang có tồn mà chưa có giá vốn nào.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Mặt hàng</th>
                                    <th scope="col">Tồn hiện tại</th>
                                    <th scope="col">Giá bán</th>
                                    <th scope="col">Giá vốn mỗi cái</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matHang as $sp)
                                    <tr>
                                        <td>
                                            {{ $sp->name }}
                                            @if($sp->product_code)
                                                <span class="d-block admin-page-subtitle small">{{ $sp->product_code }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <label class="visually-hidden" for="sl-{{ $sp->id }}">
                                                Số lượng của {{ $sp->name }}
                                            </label>
                                            <input type="number" id="sl-{{ $sp->id }}"
                                                   name="items[{{ $sp->id }}][quantity]"
                                                   class="form-control form-control-sm" style="max-width:7rem"
                                                   min="0"
                                                   value="{{ old('items.' . $sp->id . '.quantity', $sp->stock_quantity) }}">
                                        </td>
                                        <td class="admin-page-subtitle">
                                            @if($sp->base_price !== null)
                                                <x-site.money :amount="(string) $sp->base_price" />
                                            @else
                                                chưa đặt
                                            @endif
                                        </td>
                                        <td>
                                            <label class="visually-hidden" for="gv-{{ $sp->id }}">
                                                Giá vốn mỗi cái của {{ $sp->name }}
                                            </label>
                                            <input type="number" id="gv-{{ $sp->id }}"
                                                   name="items[{{ $sp->id }}][unit_cost]"
                                                   class="form-control form-control-sm" style="max-width:9rem"
                                                   min="1" step="1" placeholder="chưa nhớ"
                                                   value="{{ old('items.' . $sp->id . '.unit_cost') }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <div class="col-lg-4">
                <div class="admin-panel p-4">

                    <div class="mb-3">
                        <label class="form-label" for="received_at">Ngày chốt tồn</label>
                        <input type="date" id="received_at" name="received_at" required
                               class="form-control @error('received_at') is-invalid @enderror"
                               value="{{ old('received_at', \App\Services\Time\Gio::choONgay(now())) }}"
                               max="{{ \App\Services\Time\Gio::choONgay(now()) }}">
                        <x-form-error name="received_at" />
                        <div class="form-text">
                            Đơn bán <strong>trước</strong> ngày này vẫn không có giá vốn.
                            Nên chọn ngày bắt đầu thật sự dùng hệ thống, đừng chọn hôm nay
                            nếu cửa hàng đã bán từ trước.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="note">Ghi chú</label>
                        <textarea id="note" name="note" rows="3" maxlength="500"
                                  class="form-control @error('note') is-invalid @enderror"
                                  placeholder="Giá vốn ước tính theo sổ tay cũ.">{{ old('note') }}</textarea>
                        <x-form-error name="note" />
                    </div>

                    <button type="submit" class="btn btn-primary-brand w-100">Lập phiếu tồn đầu kỳ</button>

                    <p class="admin-page-subtitle small mt-2 mb-0">
                        Lập xong là phiếu nháp. Kiểm lại rồi mới bấm “Ghi sổ”.
                    </p>
                </div>
            </div>
        </div>
    </form>
@endif

@endsection
