{{-- KHUNG CHAT NỔI: tab Trợ lý AI và tab Nhắn cửa hàng (nhân viên thật). --}}
@php
    $troLy = app(\App\Services\AI\ShoppingAdvisor::class);
    $daCauHinh = $troLy->configured();
    $lichSu = $daCauHinh ? $troLy->lichSu() : [];
    $nguoi = auth()->user();
    $nhanDuoc = $nguoi && $nguoi->role === \App\Enums\UserRole::Customer && $nguoi->hasVerifiedEmail();
@endphp

<details class="ai-chat" data-ai-chat @if($nhanDuoc) data-chat-unread-url="{{ route('shop.chat.unread') }}" @endif>
    <summary class="ai-chat__toggle" aria-label="Tư vấn &amp; nhắn tin" title="Tư vấn &amp; nhắn tin">
        <x-site.icon name="chat" />
        <span class="visually-hidden">Tư vấn &amp; nhắn tin</span>
        <span class="chat-badge chat-badge--toggle" data-chat-badge hidden></span>
    </summary>

    <div class="ai-chat__panel" data-ai-chat-panel>
        <button type="button" class="chat-keo" data-chat-keo
                aria-label="Kéo để đổi kích thước khung chat (bấm đúp để về mặc định)"
                title="Kéo để đổi kích thước — bấm đúp để về mặc định"></button>
        <div class="chat-tabs" role="tablist">
            <button type="button" class="chat-tabs__tab is-active" role="tab" aria-selected="true" data-chat-tab="ai">
                Trợ lý AI
            </button>
            <button type="button" class="chat-tabs__tab" role="tab" aria-selected="false" data-chat-tab="shop">
                Nhắn cửa hàng <span class="chat-badge" data-chat-badge hidden></span>
            </button>
        </div>

        <div data-chat-pane="ai">
            <div class="ai-chat__head">
                <strong>Trợ lý {{ config('app.name', 'Angevil') }}</strong>
                <span class="text-caption d-block">Trả lời theo sản phẩm, giá, tồn kho và hướng dẫn chăm cây của cửa hàng.</span>
            </div>

            @unless($daCauHinh)
                <p class="ai-chat__status" data-ai-chua-cau-hinh>Trợ lý AI chưa được cấu hình.</p>
            @endunless

            <div class="ai-chat__log" data-ai-log aria-live="polite">
                @foreach($lichSu as $tin)
                    <p class="ai-chat__msg ai-chat__msg--{{ $tin['role'] === 'user' ? 'user' : 'ai' }}">{{ $tin['text'] }}</p>
                @endforeach
            </div>

            <form class="ai-chat__form" action="{{ route('shop.ai.ask') }}" method="POST" data-ai-form>
                @csrf
                <label class="visually-hidden" for="ai-cau-hoi">Câu hỏi cho trợ lý</label>
                <textarea id="ai-cau-hoi" name="cau_hoi" rows="2" required
                          maxlength="{{ (int) config('ai.max_message_length', 500) }}"
                          class="form-control"
                          placeholder="{{ $daCauHinh ? 'Ví dụ: cây nào dễ chăm để bàn làm việc?' : 'Trợ lý AI chưa được cấu hình' }}"
                          @disabled(! $daCauHinh)></textarea>

                <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                    <button type="button" class="btn btn-ghost btn-sm" data-ai-reset data-url="{{ route('shop.ai.reset') }}" @disabled(! $daCauHinh)>
                        Cuộc trò chuyện mới
                    </button>
                    <button type="submit" class="btn btn-primary-brand btn-sm" @disabled(! $daCauHinh)>Gửi</button>
                </div>
            </form>

            <p class="text-caption mb-0 mt-2">AI có thể nhầm — giá và tồn kho chính xác nhất ở trang sản phẩm.</p>
        </div>

        <div data-chat-pane="shop" hidden>
            <div class="ai-chat__head">
                <strong>Nhân viên {{ \App\Services\Shop\StoreProfile::name() }}</strong>
                <span class="text-caption d-block">Người thật trả lời, trong giờ làm việc.</span>
            </div>

            @guest
                <p class="ai-chat__status">
                    <a href="{{ route('login', ['redirect' => route('shop.chat.index', [], false)]) }}">Đăng nhập</a>
                    để nhắn tin với cửa hàng.
                </p>
            @else
                @if($nhanDuoc)
                    <x-chat.thread :lazy="true" />
                    <a href="{{ route('shop.chat.index') }}" class="text-caption d-inline-block mt-2">Mở toàn trang</a>
                @elseif($nguoi->role === \App\Enums\UserRole::Customer)
                    <p class="ai-chat__status">
                        <a href="{{ route('verification.notice') }}">Xác thực email</a> để nhắn tin với cửa hàng.
                    </p>
                @else
                    <p class="ai-chat__status">Tài khoản quản trị trả lời khách trong trang quản trị.</p>
                @endif
            @endguest
        </div>
    </div>
</details>
