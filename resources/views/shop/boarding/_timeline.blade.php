{{-- Nhật ký phiếu chăm hộ: dùng chung cho khách và quản trị. --}}
<ol class="boarding-timeline">
    @foreach($phieu->events as $e)
        <li class="boarding-timeline__item">
            <span class="boarding-timeline__time"><x-site.time :at="$e->created_at" format="d/m/Y H:i" /></span>
            <span>
                @if($e->status)
                    <strong>{{ $e->status->label() }}</strong>
                @endif
                @if($e->note)
                    <span class="d-block">{{ $e->note }}</span>
                @endif
                @if($e->photo)
                    <a href="{{ asset('storage/' . $e->photo) }}" target="_blank" rel="noopener">
                        <img src="{{ asset('storage/' . $e->photo) }}" alt="Ảnh cây do cửa hàng gửi" class="boarding-timeline__photo" loading="lazy">
                    </a>
                @endif
            </span>
        </li>
    @endforeach
</ol>
