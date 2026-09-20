{{-- Biểu mẫu chung cho thêm và sửa người dùng. --}}
@php($dangSua = isset($user))

<div class="admin-panel p-4" style="max-width: 640px">
    <div class="mb-3">
        <label class="form-label" for="u-ten">Tên</label>
        <input id="u-ten" name="name" required maxlength="255" value="{{ old('name', $user->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror">
        <x-form-error name="name" />
    </div>

    <div class="mb-3">
        <label class="form-label" for="u-email">Email</label>
        <input id="u-email" type="email" name="email" required maxlength="255" value="{{ old('email', $user->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror">
        <x-form-error name="email" />
        @if($dangSua)
            <small class="text-muted">Đổi email thì chủ tài khoản phải xác thực lại email mới.</small>
        @endif
    </div>

    @unless($dangSua)
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="u-mk">Mật khẩu</label>
                <x-form.password-input id="u-mk" name="password" autocomplete="new-password" />
                <x-form-error name="password" />
                <small class="text-muted">Ít nhất 8 ký tự, có chữ hoa, chữ thường và chữ số.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="u-mk2">Nhập lại mật khẩu</label>
                <x-form.password-input id="u-mk2" name="password_confirmation" autocomplete="new-password" />
            </div>
        </div>
    @endunless

    <div class="mb-4">
        <label class="form-label" for="u-vai-tro">Vai trò</label>
        <select id="u-vai-tro" name="role" class="form-select @error('role') is-invalid @enderror"
                @disabled($dangSua && $user->is(auth()->user()))>
            @foreach($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', ($user->role ?? null)?->value ?? 'customer') === $role->value)>
                    {{ $role->label() }}
                </option>
            @endforeach
        </select>
        <div class="form-text">
            @foreach($roles as $role)
                <span class="d-block"><strong>{{ $role->label() }}</strong>: {{ $role->moTa() }}</span>
            @endforeach
        </div>
        @if($dangSua && $user->is(auth()->user()))
            <input type="hidden" name="role" value="{{ $user->role->value }}">
            <small class="text-muted">Không tự đổi vai trò của chính mình.</small>
        @endif
        <x-form-error name="role" />
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary-brand">{{ $dangSua ? 'Cập nhật' : 'Tạo tài khoản' }}</button>
        <a data-admin-link href="{{ $dangSua ? route('admin.users.show', $user) : route('admin.users.index') }}" class="btn btn-outline-admin">Huỷ</a>
    </div>
</div>
