@extends('layouts.admin')

@section('title', 'Người dùng')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Người dùng</h1>
    <p class="admin-page-subtitle">
        Đổi vai trò và khoá tài khoản. Khoá thì chặn được người mà giữ nguyên
        đơn hàng và đánh giá của họ — không có chức năng xoá tài khoản.
    </p>
</div>

<x-admin.filter-bar
    :action="route('admin.users.index')"
    placeholder="Tìm theo tên hoặc email…"
    :total="$users->total()"
>
    <select name="role" class="form-select" aria-label="Lọc theo vai trò">
        <option value="">Mọi vai trò</option>
        @foreach($roles as $role)
            <option value="{{ $role->value }}" @selected(request('role') === $role->value)>
                {{ $role->label() }}
            </option>
        @endforeach
    </select>

    <select name="xac_thuc" class="form-select" aria-label="Lọc theo xác thực email">
        <option value="">Mọi tình trạng email</option>
        <option value="roi" @selected(request('xac_thuc') === 'roi')>Đã xác thực</option>
        <option value="chua" @selected(request('xac_thuc') === 'chua')>Chưa xác thực</option>
    </select>
</x-admin.filter-bar>

<div class="admin-panel">

    <div class="table-responsive">

        <table class="admin-table align-middle mb-0">

            <thead>
                <tr>
                    <x-admin.sort-header khoa="ten" nhan="Tên" />
                    <x-admin.sort-header khoa="email" nhan="Email" />
                    <th>Vai trò</th>
                    <x-admin.sort-header khoa="so-don" nhan="Đơn đã mua" dau="giam" class="text-end" />
                    <x-admin.sort-header khoa="da-chi" nhan="Đã chi" dau="giam" class="text-end" />
                    <x-admin.sort-header khoa="ngay" nhan="Ngày tham gia" dau="giam" />
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>

            <tbody>

            @forelse($users as $user)

                <tr class="{{ $user->isLocked() ? 'admin-row--locked' : '' }}">
                    <td class="fw-semibold">
                        {{ $user->name }}

                        {{--
                            NGƯỜI ĐANG ĐĂNG NHẬP phải nhận ra ngay dòng
                            của chính mình: hai nút bên phải đều bị chặn
                            với dòng này, và không có dấu gì thì họ bấm,
                            nhận lỗi, rồi tưởng chức năng hỏng.
                        --}}
                        @if($user->is(auth()->user()))
                            <span class="badge text-bg-light ms-1">Bạn</span>
                        @endif
                    </td>
                    <td>
                        {{ $user->email }}

                        {{--
                            ĐÁNH DẤU TÀI KHOẢN CHƯA XÁC THỰC.

                            Chưa xác thực thì không vào được ví voucher,
                            sổ địa chỉ và lịch sử đơn. Khi khách gọi kêu
                            "không vào được mục của tôi" thì đây là chỗ
                            nhìn đầu tiên — không có dấu này thì admin
                            phải mở cơ sở dữ liệu mới biết.
                        --}}
                        @unless($user->hasVerifiedEmail())
                            <span class="badge text-bg-warning ms-1">Chưa xác thực</span>
                        @endunless
                    </td>
                    <td>
                        @if($user->isAdmin())
                            <span class="badge text-bg-primary">Quản trị viên</span>
                        @else
                            <span class="badge text-bg-light">Khách hàng</span>
                        @endif

                        @if($user->isLocked())
                            {{--
                                LÝ DO ĐẶT NGAY TRONG title.

                                Danh sách không đủ chỗ cho cả câu, nhưng
                                "vì sao tài khoản này bị khoá" là câu
                                hỏi đi liền với việc nhìn thấy nó bị
                                khoá. Bắt mở một trang khác để đọc một
                                dòng chữ là một bước thừa.
                            --}}
                            <span class="badge text-bg-danger ms-1"
                                  @if($user->lock_reason) title="{{ $user->lock_reason }}" @endif>
                                Đã khoá
                            </span>
                        @endif
                    </td>

                    {{--
                        CHỈ ĐẾM ĐƠN ĐÃ HOÀN THÀNH.

                        Đơn đang chờ hoặc đã huỷ không phải tiền cửa hàng
                        đã nhận. Gộp vào là con số nói dối, và nói dối
                        đúng ở chỗ dùng để đánh giá khách quen.
                    --}}
                    <td class="text-end">{{ $user->completed_orders_count }}</td>
                    <td class="text-end">
                        @if($user->spent_total)
                            <x-site.money :amount="(float) $user->spent_total" />
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td><small class="text-muted"><x-site.time :at="$user->created_at" format="d/m/Y" /></small></td>

                    <td class="text-end">
                        @if($user->is(auth()->user()))

                            {{--
                                Không hiện nút cho chính mình.

                                Controller đã chặn cả hai thao tác này
                                (xem updateRole/updateLock), nhưng hiện
                                một cái nút chắc chắn báo lỗi là mời
                                người ta bấm vào chỗ không dùng được.
                                Chặn ở máy chủ là để an toàn; không hiện
                                nút là để không lừa người dùng.
                            --}}
                            <span class="text-muted small">—</span>

                        @else

                            <div class="d-inline-flex gap-2 align-items-center">

                                {{--
                                    ĐỔI VAI TRÒ — gửi ngay khi chọn nếu
                                    có JavaScript, còn không thì bấm nút
                                    "Lưu" bên cạnh. Vẫn là một biểu mẫu
                                    POST bình thường.
                                --}}
                                <form method="POST"
                                      action="{{ route('admin.users.role', $user) }}"
                                      class="d-inline-flex gap-1">
                                    @csrf
                                    @method('PATCH')

                                    <select name="role" class="form-select form-select-sm w-auto"
                                            aria-label="Vai trò của {{ $user->name }}">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->value }}"
                                                    @selected($user->role === $role)>
                                                {{ $role->label() }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Lưu
                                    </button>
                                </form>

                                {{--
                                    KHOÁ / MỞ KHOÁ.

                                    Khoá thì hỏi lý do — dòng đó hiện
                                    cho chính người bị khoá đọc ở màn
                                    hình đăng nhập. Mở khoá thì không
                                    hỏi gì, chỉ cần một nút.
                                --}}
                                <form method="POST"
                                      action="{{ route('admin.users.lock', $user) }}"
                                      @unless($user->isLocked())
                                          onsubmit="return promptLockReason(this);"
                                      @endunless>
                                    @csrf
                                    @method('PATCH')

                                    <input type="hidden" name="khoa"
                                           value="{{ $user->isLocked() ? 0 : 1 }}">
                                    <input type="hidden" name="ly_do" value="">

                                    <button type="submit"
                                            class="btn btn-sm {{ $user->isLocked() ? 'btn-outline-success' : 'btn-outline-danger' }}">
                                        {{ $user->isLocked() ? 'Mở khoá' : 'Khoá' }}
                                    </button>
                                </form>

                            </div>

                        @endif
                    </td>
                </tr>

            @empty

                <x-admin.empty-row :colspan="7" empty="Chưa có người dùng nào." />

            @endforelse

            </tbody>

        </table>

    </div>

    @if($users->hasPages())
        <div class="p-3 border-top">
            {{ $users->links() }}
        </div>
    @endif

</div>

@endsection

@push('scripts')
<script>
/*
 * HỎI LÝ DO KHOÁ.
 *
 * prompt() chứ không phải một hộp thoại tự dựng: đây là một ô nhập duy
 * nhất, và dựng modal riêng cho nó là thêm chừng năm mươi dòng HTML/CSS
 * để làm lại một thứ trình duyệt đã có sẵn.
 *
 * KHÔNG CÓ JAVASCRIPT VẪN KHOÁ ĐƯỢC: biểu mẫu gửi đi với ly_do rỗng, và
 * máy chủ nhận vì trường đó nullable. Khi ấy người bị khoá thấy câu mặc
 * định "vui lòng liên hệ cửa hàng" — kém cụ thể hơn, nhưng không hỏng.
 *
 * Bấm Huỷ (prompt trả về null) thì KHÔNG gửi gì cả. Coi đó là "khoá mà
 * không nêu lý do" là hiểu sai ý người dùng theo hướng nguy hiểm nhất.
 */
function promptLockReason(form) {
    const lyDo = window.prompt(
        'Lý do khoá tài khoản này? (người dùng sẽ đọc được dòng này khi đăng nhập)'
    );

    if (lyDo === null) return false;

    form.querySelector('input[name="ly_do"]').value = lyDo.trim();

    return true;
}
</script>
@endpush
