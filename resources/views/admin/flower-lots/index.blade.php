@extends('layouts.admin')

@section('title', 'Lô hoa')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Lô hoa tươi</h1>
        <p class="admin-page-subtitle mb-0">
            Hoa không đếm theo cành mà theo lô. Mỗi lần lấy hàng là một lô;
            dùng hết thì đóng lô, và <strong>lúc đóng tiền mới vào giá vốn</strong>.
        </p>
    </div>

    <a data-admin-link href="{{ route('admin.flower-lots.create') }}" class="btn btn-primary-brand">
        Ghi lô mới
    </a>
</div>

{{--
    NHẮC LÔ QUÊN ĐÓNG — ĐẶT TRÊN CÙNG, CÓ CHỦ Ý.

    Quên đóng lô làm giá vốn hoa thấp hơn sự thật và lãi gộp cao hơn sự
    thật. Sai theo hướng dễ chịu là hướng không ai tự đi tìm, nên nó phải
    tự tìm đến người dùng.
--}}
@if($quenDong->isNotEmpty())
    <div class="admin-panel p-4 mb-3 border-warning">
        <h2 class="h6 fw-bold mb-2">
            {{ $quenDong->count() }} lô mở quá {{ $ngayNhac }} ngày
        </h2>
        <p class="admin-page-subtitle">
            Hoa tươi giữ được 3–7 ngày. Những lô này gần như chắc chắn đã hết mà chưa đóng —
            chừng nào chưa đóng, tiền của chúng chưa vào giá vốn và lãi gộp hoa đang cao hơn sự thật.
        </p>
        <ul class="mb-0 ps-3 admin-page-subtitle">
            @foreach($quenDong as $l)
                <li>
                    {{ $l->code }} — {{ $l->kind?->name }},
                    lấy ngày <x-site.time :at="$l->purchased_at" format="d/m/Y" />
                    ({{ $l->soNgayMo() }} ngày trước)
                </li>
            @endforeach
        </ul>
    </div>
@endif

<x-admin.filter-bar :action="route('admin.flower-lots.index')">
    <select name="trang_thai" class="form-select" aria-label="Lọc theo tình trạng">
        <option value="">Mọi tình trạng</option>
        @foreach($trangThai as $tt)
            <option value="{{ $tt->value }}" @selected(request('trang_thai') === $tt->value)>
                {{ $tt->label() }}
            </option>
        @endforeach
    </select>

    <select name="loai_hoa" class="form-select" aria-label="Lọc theo loại hoa">
        <option value="">Mọi loại hoa</option>
        @foreach($loaiHoa as $lh)
            <option value="{{ $lh->id }}" @selected((int) request('loai_hoa') === $lh->id)>
                {{ $lh->name }}
            </option>
        @endforeach
    </select>

    <select name="nha_cung_cap" class="form-select" aria-label="Lọc theo nhà cung cấp">
        <option value="">Mọi nhà cung cấp</option>
        @foreach($nhaCungCap as $ncc)
            <option value="{{ $ncc->id }}" @selected((int) request('nha_cung_cap') === $ncc->id)>
                {{ $ncc->name }}
            </option>
        @endforeach
    </select>
</x-admin.filter-bar>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Lô</th>
                    <th scope="col">Loại hoa</th>
                    <th scope="col">Nhà cung cấp</th>
                    <th scope="col">Số lượng</th>
                    <th scope="col">Tổng tiền</th>
                    <th scope="col">Đơn giá</th>
                    <th scope="col">Hao hụt</th>
                    <th scope="col">Tình trạng</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lo as $l)
                    <tr>
                        <td>
                            {{ $l->code }}
                            <span class="d-block admin-page-subtitle small">
                                <x-site.time :at="$l->purchased_at" format="d/m/Y" />
                            </span>
                        </td>
                        <td>{{ $l->kind?->name ?? '—' }}</td>
                        <td>
                            {{-- Bản chụp tên, không phải tên hiện tại. --}}
                            {{ $l->supplier_name ?? $l->supplier?->name ?? '—' }}
                        </td>
                        <td>{{ rtrim(rtrim(number_format((float) $l->quantity, 2, ',', '.'), '0'), ',') }}
                            {{ $l->unit->label() }}</td>
                        <td><x-site.money :amount="(string) $l->total_cost" /></td>
                        <td>
                            @if($l->donGia())
                                <x-site.money :amount="$l->donGia()" />
                                <span class="admin-page-subtitle small">/{{ $l->unit->label() }}</span>
                            @else
                                <span class="admin-page-subtitle">—</span>
                            @endif
                        </td>
                        <td>
                            @if($l->daDong())
                                {{ rtrim(rtrim(number_format((float) $l->hao_hut, 2, ',', '.'), '0'), ',') }}
                                @if($l->tiLeHaoHut() !== null)
                                    <span class="admin-page-subtitle small">({{ $l->tiLeHaoHut() }}%)</span>
                                @endif
                            @else
                                <span class="admin-page-subtitle">chưa đóng</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $l->status->tone() }}">{{ $l->status->label() }}</span>

                            @unless($l->daDong())
                                <button type="button" class="btn btn-sm btn-outline-admin mt-1"
                                        data-bs-toggle="collapse" data-bs-target="#dong-{{ $l->id }}">
                                    Đóng lô
                                </button>
                            @endunless

                            {{-- Sửa / xoá chỉ hiện khi bấm được: lô còn mở và chưa ghi trả hàng. --}}
                            @if(\App\Services\Inventory\FlowerLotService::conSuaDuoc($l))
                                <div class="d-flex gap-1 mt-1">
                                    <a data-admin-link href="{{ route('admin.flower-lots.edit', $l) }}"
                                       class="btn btn-sm btn-outline-admin">Sửa</a>

                                    <form method="POST" action="{{ route('admin.flower-lots.destroy', $l) }}"
                                          onsubmit="return confirm('Xoá lô {{ $l->code }}? Chỉ dùng khi ghi nhầm hoặc ghi trùng.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Xoá</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>

                    @unless($l->daDong())
                        <tr class="collapse" id="dong-{{ $l->id }}">
                            <td colspan="8">
                                <form method="POST" action="{{ route('admin.flower-lots.close', $l) }}"
                                      class="d-flex flex-wrap align-items-end gap-2">
                                    @csrf
                                    @method('PATCH')

                                    <div>
                                        <label class="form-label small mb-1" for="hao-{{ $l->id }}">
                                            Hao hụt ({{ $l->unit->label() }})
                                        </label>
                                        <input type="number" id="hao-{{ $l->id }}" name="hao_hut"
                                               class="form-control form-control-sm" style="max-width:8rem"
                                               min="0" max="{{ $l->quantity }}" step="0.01" value="0">
                                    </div>

                                    <div>
                                        <label class="form-label small mb-1" for="cl-{{ $l->id }}">Chất lượng</label>
                                        <select id="cl-{{ $l->id }}" name="quality" class="form-select form-select-sm">
                                            <option value="">— chưa đánh giá —</option>
                                            @foreach(\App\Enums\FlowerQuality::cases() as $cl)
                                                <option value="{{ $cl->value }}"
                                                        @selected($l->quality?->value === $cl->value)>
                                                    {{ $cl->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex-grow-1" style="min-width:14rem">
                                        <label class="form-label small mb-1" for="gc-{{ $l->id }}">Ghi chú</label>
                                        <input type="text" id="gc-{{ $l->id }}" name="note" maxlength="1000"
                                               class="form-control form-control-sm"
                                               placeholder="Hoa nở nhanh hơn mọi lần, dập nhiều ở đáy thùng…">
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-primary-brand">Đóng lô</button>
                                </form>

                                <p class="admin-page-subtitle small mt-2 mb-0">
                                    Hao hụt <strong>không làm giảm giá vốn</strong> — tiền đã trả rồi.
                                    Nó là thước đo chất lượng: cùng một giá, vựa hao 5% và vựa hao 20%
                                    không phải hai lựa chọn ngang nhau.
                                </p>
                            </td>
                        </tr>
                    @endunless
                @empty
                    <x-admin.empty-row :colspan="8">
                        Chưa ghi lô hoa nào.
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $lo->links() }}</div>

@endsection
