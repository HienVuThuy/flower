{{-- ---------- Thiết bị đang đăng nhập ---------- --}}
<div class="surface-card p-4 mt-4">

    <h2 class="text-h4 mb-1">Thiết bị đang đăng nhập</h2>
    <p class="text-caption mb-4">
        Thấy thiết bị lạ thì đăng xuất nó ngay, rồi đổi mật khẩu.
    </p>

    @if(! $sessionsSupported)

        <p class="text-caption mb-0">
            Máy chủ đang lưu phiên đăng nhập theo cách không liệt kê được
            (SESSION_DRIVER khác <code>database</code>), nên phần này tạm
            thời không hiển thị. Đổi mật khẩu vẫn đăng xuất được mọi thiết bị.
        </p>

    @else

        <ul class="session-list">
            @foreach($sessions as $session)
                <li class="session-list__item {{ $session['current'] ? 'is-current' : '' }}">

                    <div class="session-list__info">
                        <span class="session-list__device">
                            {{ $session['device'] }}
                            @if($session['current'])
                                <span class="session-list__badge">Thiết bị này</span>
                            @endif
                        </span>
                        <span class="session-list__meta">
                            IP {{ $session['ip'] }}
                            &middot; hoạt động lúc <x-site.time :at="$session['lastActive']" />
                        </span>
                    </div>

                    @unless($session['current'])
                        <form action="{{ route('shop.profile.sessions.revoke') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="session_id" value="{{ $session['id'] }}">
                            <button type="submit" class="btn btn-ghost btn-sm">
                                Đăng xuất
                            </button>
                        </form>
                    @endunless

                </li>
            @endforeach
        </ul>

        <p class="text-caption mt-3 mb-0">
            Phiên tự hết hạn sau {{ config('session.lifetime') }} phút không hoạt động,
            nên danh sách này có thể ngắn hơn số lần bạn thật sự đăng nhập.
        </p>

        <p class="text-caption mb-0">
            Tên trình duyệt chỉ là phỏng đoán từ thông tin trình duyệt tự khai,
            và nhiều trình duyệt (Cốc Cốc, Brave&hellip;) cố ý khai mình là Chrome.
            Để nhận ra thiết bị của mình, hãy đối chiếu <strong>địa chỉ IP</strong>
            và <strong>lần hoạt động gần nhất</strong> — hai thông tin đó đáng tin hơn.
        </p>

    @endif

</div>
