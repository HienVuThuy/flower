{{-- YÊU CẦU THÊM của phiếu chăm hộ — cùng một danh sách cho khách và cửa hàng.
     $laAdmin: hiện nút báo giá / không nhận / ghi hộ khách / đã làm; ngược lại hiện nút đồng ý / không làm cho khách. --}}
@php
    use App\Enums\BoardingExtraStatus;
    use App\Models\BoardingExtra;
    use App\Services\Shop\Money;

    /* Khi phiếu đang báo giá, mọi việc được báo giá / xác nhận trong báo giá — không tách lẻ từng việc. */
    $dangBaoGia = in_array($phieu->status, [\App\Enums\BoardingStatus::ChoDuyet, \App\Enums\BoardingStatus::ChoKhachDuyet], true);
@endphp

@if($phieu->extras->isEmpty())
    <p class="text-caption mb-0">Chưa có việc làm thêm nào.</p>
@else
    <ul class="boarding-extras" data-viec-them>
        @foreach($phieu->extras as $x)
            <li class="boarding-extras__item" data-viec="{{ $x->id }}">
                <div class="boarding-extras__head">
                    <strong>{{ $x->title }}</strong>
                    <span class="badge text-bg-{{ $x->status->tone() }}">{{ $x->status->label() }}</span>
                </div>
                <span class="text-caption d-block">
                    {{ $x->proposed_by === BoardingExtra::KHACH ? 'Khách yêu cầu' : 'Cửa hàng đề xuất' }}
                    · <x-site.time :at="$x->created_at" format="d/m/Y" />
                    @if($x->price !== null) · <strong>{{ Money::format((string) $x->price) }}</strong> @endif
                </span>
                @if($x->customer_note)<span class="d-block small">Khách ghi: {{ $x->customer_note }}</span>@endif
                @if($x->shop_note)<span class="d-block small">Cửa hàng: {{ $x->shop_note }}</span>@endif

                @if($dangBaoGia)
                    @if($x->status === BoardingExtraStatus::ChoBaoGia || $x->status === BoardingExtraStatus::ChoKhach)
                        <span class="d-block small text-caption mt-1">Nằm trong báo giá của phiếu.</span>
                    @endif
                @elseif($laAdmin)
                    @if($x->status === BoardingExtraStatus::ChoBaoGia)
                        <form method="POST" action="{{ route('admin.boarding.extra.quote', [$phieu, $x]) }}" class="d-flex flex-wrap gap-2 mt-2">
                            @csrf @method('PATCH')
                            <input type="number" name="gia" min="0" step="1000" required class="form-control form-control-sm" style="max-width: 9rem" placeholder="Giá" aria-label="Giá việc {{ $x->title }}">
                            <input type="text" name="ghi_chu" maxlength="500" class="form-control form-control-sm" style="flex: 1 1 10rem" placeholder="Ghi chú cho khách" aria-label="Ghi chú">
                            <button type="submit" class="btn btn-sm btn-primary-brand">Báo giá</button>
                        </form>
                        <form method="POST" action="{{ route('admin.boarding.extra.reject', [$phieu, $x]) }}" class="d-flex flex-wrap gap-2 mt-2">
                            @csrf @method('PATCH')
                            <input type="text" name="ly_do" required maxlength="500" class="form-control form-control-sm" style="flex: 1 1 10rem" placeholder="Lý do không nhận" aria-label="Lý do không nhận">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Không nhận</button>
                        </form>
                    @elseif($x->status === BoardingExtraStatus::ChoKhach)
                        <form method="POST" action="{{ route('admin.boarding.extra.answer', [$phieu, $x]) }}" class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                            @csrf @method('PATCH')
                            <span class="small admin-page-subtitle">Khách trả lời qua điện thoại / tại quầy:</span>
                            <button type="submit" name="dong_y" value="1" class="btn btn-sm btn-outline-admin">Ghi: khách đồng ý</button>
                            <button type="submit" name="dong_y" value="0" class="btn btn-sm btn-outline-secondary">Ghi: khách không làm</button>
                        </form>
                    @elseif($x->status === BoardingExtraStatus::DaDongY)
                        <form method="POST" action="{{ route('admin.boarding.extra.done', [$phieu, $x]) }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 mt-2">
                            @csrf @method('PATCH')
                            <input type="text" name="ghi_chu" maxlength="500" class="form-control form-control-sm" style="flex: 1 1 10rem" placeholder="Làm thế nào, cây ra sao" aria-label="Ghi chú">
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" style="max-width: 14rem" aria-label="Ảnh">
                            <button type="submit" class="btn btn-sm btn-primary-brand">Đã làm</button>
                        </form>
                    @endif
                @elseif($x->status === BoardingExtraStatus::ChoKhach)
                    <form method="POST" action="{{ route('shop.boarding.extra.answer', [$phieu, $x]) }}" class="d-flex flex-wrap gap-2 mt-2">
                        @csrf
                        <button type="submit" name="dong_y" value="1" class="btn btn-sm btn-primary-brand">Đồng ý {{ Money::format((string) $x->price) }}</button>
                        <button type="submit" name="dong_y" value="0" class="btn btn-sm btn-ghost">Không làm</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@endif
