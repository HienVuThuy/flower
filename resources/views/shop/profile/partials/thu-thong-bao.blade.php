{{-- ---------- Thư thông báo ---------- --}}
<div class="surface-card p-4 mt-4">

    <h2 class="text-h4 mb-1">Thư thông báo</h2>
    <p class="text-caption mb-4">
        Bạn chọn có nhận thư khi đơn hàng đổi trạng thái hay không.
    </p>

    <form action="{{ route('shop.profile.notifications') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="checkbox"
                   name="notify_order_updates"
                   id="notify_order_updates"
                   value="1"
                   @checked($user->wantsOrderUpdates())>
            <label class="form-check-label" for="notify_order_updates">
                Báo cho tôi khi đơn được xác nhận, đang giao hoặc đã giao
            </label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="checkbox"
                   name="notify_care_reminders"
                   id="notify_care_reminders"
                   value="1"
                   @checked($user->notify_care_reminders !== false)>
            <label class="form-check-label" for="notify_care_reminders">
                Nhắc tôi tưới nước / bón phân cho cây đã mua
            </label>
            <div class="form-text">
                {{-- Công tắc TỔNG. Nói rõ quan hệ với trang
                     Lịch chăm cây, nếu không khách tắt ở đây
                     rồi vẫn thấy danh sách lịch "đang bật" bên
                     kia và không hiểu cái nào thắng. --}}
                Đây là công tắc tổng. Muốn tắt riêng từng cây thì vào
                <a href="{{ route('shop.care.index') }}">Lịch chăm cây</a>.
            </div>
        </div>

        {{--
            Nói thẳng thứ KHÔNG tắt được, ngay tại chỗ tắt.
            Người bỏ tích ở đây thường muốn "đừng gửi gì
            nữa"; nếu không nói rõ, họ sẽ tưởng đã tắt hết
            rồi bực mình khi vẫn nhận thư xác nhận đơn.
        --}}
        <p class="text-caption mb-3">
            Thư xác nhận đơn hàng và thư cảnh báo bảo mật (đổi mật khẩu,
            đặt lại mật khẩu) luôn được gửi — đó là biên nhận mua hàng và
            cảnh báo khi tài khoản có thể đang bị người khác dùng.
        </p>

        <button type="submit" class="btn btn-primary-brand">
            Lưu tuỳ chọn
        </button>
    </form>

</div>
