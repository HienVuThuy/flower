@extends('layouts.admin')

@section('title', 'Người dùng')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Người dùng</h1>
        <p class="admin-page-subtitle mb-0">
            Thêm, sửa, đổi vai trò, khoá hoặc xoá tài khoản. Nên khoá thay vì xoá: khoá chặn đăng nhập mà vẫn giữ lịch sử;
            xoá chỉ làm được khi tài khoản không còn đơn dở dang và đơn cũ vẫn được giữ.
        </p>
    </div>
    <a data-admin-link href="{{ route('admin.users.create') }}" class="btn btn-primary-brand">+ Thêm người dùng</a>
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
                        <a data-admin-link href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a>

                        @if($user->is(auth()->user()))
                            <span class="badge text-bg-light ms-1">Bạn</span>
                        @endif
                    </td>
                    <td>
                        {{ $user->email }}

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
                            <span class="badge text-bg-danger ms-1"
                                  @if($user->lock_reason) title="{{ $user->lock_reason }}" @endif>
                                Đã khoá
                            </span>
                        @endif
                    </td>

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

                            <span class="text-muted small">—</span>

                        @else

                            <div class="d-inline-flex flex-wrap gap-2 align-items-center justify-content-end" style="max-width: 250px">

                                <form method="POST"
                                      action="{{ route('admin.users.role', $user) }}"
                                      class="d-inline-flex gap-1">
                                    @csrf
                                    @method('PATCH')

                                    <select name="role" class="form-select form-select-sm w-auto"
                                            aria-label="Vai trò của {{ $user->name }}">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->value }}"
                                                    @selected($user->role === $role)
                                                    title="{{ $role->moTa() }}">
                                                {{ $role->label() }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Lưu
                                    </button>
                                </form>

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

                                <a data-admin-link href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>

                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                      onsubmit="return confirm('Xoá hẳn tài khoản {{ addslashes($user->name) }}? Không hoàn tác được — cân nhắc Khoá thay vì xoá.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xoá</button>
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

<div class="admin-panel p-4 mt-4">

    <h2 class="h5 fw-bold mb-1">Mỗi vai trò vào được khu nào</h2>
    <p class="text-muted small mb-3">
        Đổi bảng này phải sửa mã nguồn — cố ý như vậy: phân quyền là thứ
        không nên đổi được bằng một cú bấm nhầm.
    </p>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Khu vực</th>
                    @foreach(\App\Enums\UserRole::nhanSu() as $vt)
                        <th scope="col" class="text-center">{{ $vt->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach(\App\Enums\Quyen::cases() as $q)
                    <tr>
                        <th scope="row" class="fw-normal">
                            {{ $q->nhan() }}
                            <span class="d-block admin-page-subtitle small">{{ $q->moTa() }}</span>
                        </th>

                        @foreach(\App\Enums\UserRole::nhanSu() as $vt)
                            @php $co = in_array($q, $vt->quyen(), true); @endphp
                            <td class="text-center">
                                <span class="{{ $co ? 'text-success' : 'text-muted' }}">
                                    {{ $co ? 'Có' : 'Không' }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

@endsection

@push('scripts')
<script>
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
