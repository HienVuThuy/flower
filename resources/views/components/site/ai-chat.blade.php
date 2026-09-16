{{-- KHUNG CHAT TRỢ LÝ AI — "Plant & Shopping Advisor". --}}
@php
    $troLy = app(\App\Services\AI\ShoppingAdvisor::class);
    $daCauHinh = $troLy->configured();
    $lichSu = $daCauHinh ? $troLy->lichSu() : [];
@endphp

<details class="ai-chat" data-ai-chat>
    <summary class="ai-chat__toggle" aria-label="Tư vấn cây &amp; mua sắm" title="Tư vấn cây &amp; mua sắm">
        <x-site.icon name="flower2" />
        <span class="visually-hidden">Tư vấn cây &amp; mua sắm</span>
    </summary>

    <div class="ai-chat__panel">
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
</details>
