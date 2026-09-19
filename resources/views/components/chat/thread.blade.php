{{-- Một hội thoại khách ↔ cửa hàng: danh sách tin + ô gửi. Không có JavaScript vẫn gửi được. --}}
@props(['tin' => [], 'lazy' => false])

<div class="chat-thread" data-live-chat data-url-tin="{{ route('shop.chat.messages') }}" @if($lazy) data-lazy @endif>
    <div class="chat-thread__log" data-chat-log aria-live="polite">
        @foreach($tin as $t)
            <div class="chat-msg {{ $t['tu_khach'] ? 'chat-msg--me' : 'chat-msg--them' }}" data-id="{{ $t['id'] }}">
                <p class="chat-msg__text">{{ $t['noi_dung'] }}</p>
                <span class="chat-msg__meta">{{ $t['nguoi_gui'] }} · {{ $t['luc'] }}</span>
            </div>
        @endforeach

        <p class="chat-thread__empty" data-chat-empty @if(count($tin) > 0 || $lazy) hidden @endif>
            Chưa có tin nhắn. Hỏi cửa hàng về đơn hàng, cách chăm cây hay đặt hoa theo yêu cầu.
        </p>
    </div>

    <form method="POST" action="{{ route('shop.chat.send') }}" class="chat-thread__form" data-chat-form>
        @csrf
        <label class="visually-hidden" for="chat-noi-dung-{{ $lazy ? 'noi' : 'trang' }}">Tin nhắn</label>
        <textarea id="chat-noi-dung-{{ $lazy ? 'noi' : 'trang' }}" name="noi_dung" rows="2" required
                  maxlength="{{ \App\Services\Chat\LiveChat::DO_DAI_TOI_DA }}"
                  class="form-control" placeholder="Nhập tin nhắn… (Enter để gửi)">{{ old('noi_dung') }}</textarea>
        <p class="chat-thread__error" data-chat-error hidden></p>
        <x-form-error name="noi_dung" />
        <button type="submit" class="btn btn-primary-brand btn-sm">Gửi</button>
    </form>
</div>
