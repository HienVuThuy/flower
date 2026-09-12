@extends('layouts.admin')

@section('title', 'Loại hoa thu mua')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Loại hoa thu mua</h1>
    <p class="admin-page-subtitle">
        Thứ người ta gọi tên khi ra chợ: “hồng đỏ”, “cúc vàng”, “ly trắng”.
        Đây <strong>không phải</strong> danh mục sinh học ở mục Loại cây — đó là trục khác.
    </p>
</div>

<div class="row g-3">

    <div class="col-lg-5">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Thêm loại hoa</h2>

            <form method="POST" action="{{ route('admin.flower-kinds.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label" for="name">Tên <span aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" maxlength="120" required
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="Hồng đỏ Đà Lạt">
                    <x-form-error name="name" />
                    <div class="form-text">
                        Mỗi loại một dòng. Hai dòng cùng một loại thì không so giá với nhau được.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="default_unit">Đơn vị hay mua</label>
                    <select id="default_unit" name="default_unit"
                            class="form-select @error('default_unit') is-invalid @enderror">
                        @foreach($donVi as $dv)
                            <option value="{{ $dv->value }}" @selected(old('default_unit', 'bo') === $dv->value)>
                                {{ $dv->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="default_unit" />
                    {{-- Chỉ là gợi ý điền sẵn: đơn vị thật nằm trên từng lô, vì có
                         hôm mua theo bó ở vựa có hôm mua theo cân ngoài chợ. --}}
                    <div class="form-text">Chỉ để điền sẵn; mỗi lô vẫn chọn được đơn vị riêng.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="note">Ghi chú</label>
                    <textarea id="note" name="note" rows="2" maxlength="1000"
                              class="form-control @error('note') is-invalid @enderror"
                              placeholder="Giữ được 5–7 ngày. Mùa hè hay nở nhanh.">{{ old('note') }}</textarea>
                    <x-form-error name="note" />
                </div>

                <label class="d-flex align-items-center gap-2 mb-3">
                    <input type="checkbox" class="form-check-input" name="is_active" value="1"
                           @checked(old('is_active', true))>
                    <span>Còn lấy loại này</span>
                </label>

                <button type="submit" class="btn btn-primary-brand w-100">Thêm</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="admin-panel">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Loại hoa</th>
                            <th scope="col">Đơn vị hay mua</th>
                            <th scope="col">Số lô</th>
                            <th scope="col">Tình trạng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loaiHoa as $lh)
                            <tr>
                                <td>
                                    {{ $lh->name }}
                                    @if($lh->note)
                                        <span class="d-block admin-page-subtitle small fst-italic">{{ $lh->note }}</span>
                                    @endif
                                </td>
                                <td>{{ $lh->default_unit->label() }}</td>
                                <td>{{ $lh->lots_count }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.flower-kinds.update', $lh) }}"
                                          class="d-flex align-items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $lh->name }}">
                                        <input type="hidden" name="default_unit" value="{{ $lh->default_unit->value }}">
                                        <input type="hidden" name="note" value="{{ $lh->note }}">
                                        <input type="hidden" name="is_active" value="{{ $lh->is_active ? 0 : 1 }}">

                                        <span class="badge text-bg-{{ $lh->is_active ? 'success' : 'secondary' }}">
                                            {{ $lh->is_active ? 'Còn lấy' : 'Đã ngừng' }}
                                        </span>

                                        <button type="submit" class="btn btn-sm btn-outline-admin">
                                            {{ $lh->is_active ? 'Ngừng' : 'Lấy lại' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row :colspan="4">
                                Chưa khai loại hoa nào.
                            </x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $loaiHoa->links() }}</div>
    </div>

</div>

@endsection
