{{-- TRAO ĐỔI VỀ PHIẾU — tin nhắn chat khách ↔ nhân viên có gắn mã phiếu này.
     Cùng nằm trong hộp thư chat chung, ở đây chỉ lọc ra phần nói về phiếu. --}}
<div id="trao-doi" data-trao-doi>
    @if(! $phieu->user_id)
        <p class="text-caption mb-0">Khách lập phiếu tại quầy, không có tài khoản — trao đổi qua số {{ $phieu->contact_phone }}.</p>
    @else
        @if($phieu->messages->isEmpty())
            <p class="text-caption">Chưa có tin nhắn nào về phiếu này.</p>
        @else
            <ol class="boarding-chat">
                @foreach($phieu->messages as $tin)
                    @php $tuKhach = $tin->tuKhach(); @endphp
                    <li class="boarding-chat__tin {{ $tuKhach ? 'is-khach' : 'is-cua-hang' }}">
                        <span class="boarding-chat__nguoi">
                            {{ $tuKhach ? ($laAdmin ? $phieu->tenKhach() : 'Bạn') : ($laAdmin ? ($tin->sender?->name ?? 'Cửa hàng') : \App\Services\Shop\StoreProfile::name()) }}
                            · <x-site.time :at="$tin->created_at" format="d/m H:i" />
                        </span>
                        <span class="boarding-chat__chu">{{ $tin->content }}</span>
                    </li>
                @endforeach
            </ol>
        @endif

        <form method="POST" action="{{ $laAdmin ? route('admin.boarding.message', $phieu) : route('shop.boarding.message', $phieu) }}" class="mt-2">
            @csrf
            <label class="visually-hidden" for="td-noi-dung">Tin nhắn</label>
            <textarea id="td-noi-dung" name="noi_dung" rows="2" maxlength="1000" required
                      class="form-control mb-2 @error('noi_dung') is-invalid @enderror"
                      placeholder="{{ $laAdmin ? 'Nhắn khách: hỏi thêm về cây, giải thích báo giá…' : 'Hỏi nhân viên về báo giá, cách chăm, ngày giao cây…' }}">{{ old('noi_dung') }}</textarea>
            <x-form-error name="noi_dung" />
            <button type="submit" class="btn btn-sm {{ $laAdmin ? 'btn-outline-admin' : 'btn-secondary-brand' }}">Gửi tin nhắn</button>
            <span class="text-caption ms-2">Tin nhắn cũng hiện trong mục {{ $laAdmin ? 'Hỗ trợ khách hàng' : 'Tin nhắn' }}.</span>
        </form>
    @endif
</div>
