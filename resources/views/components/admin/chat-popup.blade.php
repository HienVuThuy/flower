{{-- Khung chat hỗ trợ khách trong trang quản trị — nằm ngoài <main> nên không mất khi chuyển trang. --}}
<div class="admin-chat" data-admin-chat
     data-url-hoi-thoai="{{ route('admin.chat.users') }}"
     data-url-tin="{{ url('admin/tin-nhan') }}"
     data-url-khach="{{ url('admin/users') }}">
    <button type="button" class="admin-chat__toggle" data-admin-chat-toggle aria-expanded="false">
        <x-site.icon name="chat" />
        <span>Tin nhắn</span>
        <span class="chat-badge" data-admin-chat-badge hidden></span>
    </button>

    <div class="admin-chat__panel" data-admin-chat-panel hidden>
        <button type="button" class="chat-keo" data-chat-keo
                aria-label="Kéo để đổi kích thước khung chat (bấm đúp để về mặc định)"
                title="Kéo để đổi kích thước — bấm đúp để về mặc định"></button>
        <div class="admin-chat__head">
            <strong>Hỗ trợ khách hàng</strong>
            <button type="button" class="btn-close" data-admin-chat-close aria-label="Đóng"></button>
        </div>

        <div class="admin-chat__body">
            <aside class="admin-chat__list">
                <input type="search" class="form-control form-control-sm" placeholder="Tìm khách…" data-admin-chat-tim>
                <div class="admin-chat__users" data-admin-chat-users>
                    <p class="admin-chat__hint">Đang tải…</p>
                </div>
            </aside>

            <section class="admin-chat__thread">
                <div class="admin-chat__who" data-admin-chat-who>Chọn một khách để xem tin nhắn.</div>
                <div class="chat-thread__log admin-chat__log" data-admin-chat-log aria-live="polite"></div>
                <form class="chat-thread__form" data-admin-chat-form hidden>
                    @csrf
                    <label class="visually-hidden" for="admin-chat-noi-dung">Trả lời</label>
                    <textarea id="admin-chat-noi-dung" name="noi_dung" rows="2" required
                              maxlength="{{ \App\Services\Chat\LiveChat::DO_DAI_TOI_DA }}"
                              class="form-control form-control-sm" placeholder="Nhập câu trả lời… (Enter để gửi)"></textarea>
                    <p class="chat-thread__error" data-chat-error hidden></p>
                    <button type="submit" class="btn btn-primary-brand btn-sm">Gửi</button>
                </form>
            </section>
        </div>
    </div>
</div>
