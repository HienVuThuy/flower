@extends('layouts.admin')

@section('title', 'Nhật ký thao tác')

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="admin-page-title">Nhật ký thao tác</h1>
            <p class="admin-page-subtitle">
                Ai đã thay đổi gì trong khu quản trị. Chỉ xem được, không sửa và không xoá —
                một nhật ký sửa được thì không dùng để đối chiếu.
            </p>

            {{-- NÓI RÕ GIỚI HẠN, NGAY TRÊN TRANG. --}}
            <p class="admin-page-subtitle mb-0">
                <strong>Lưu ý:</strong> chỉ ghi những thay đổi thực hiện qua màn hình quản trị.
                Sửa thẳng cơ sở dữ liệu (phpMyAdmin, dòng lệnh) sẽ không xuất hiện ở đây,
                nên hãy đối chiếu với dữ liệu thật trước khi kết luận.
            </p>
        </div>
    </div>

    <x-admin.filter-bar
        :action="route('admin.activity-logs.index')"
        placeholder="Tìm theo mã đơn, tên sản phẩm…"
        :total="$logs->total()"
    >
        <select name="nhom" class="form-select" aria-label="Lọc theo nhóm việc">
            <option value="">Mọi nhóm việc</option>
            @foreach($nhomViec as $ma => $nhan)
                <option value="{{ $ma }}" @selected(request('nhom') === $ma)>{{ $nhan }}</option>
            @endforeach
        </select>

        <select name="nguoi" class="form-select" aria-label="Lọc theo người thực hiện">
            <option value="">Mọi người thực hiện</option>
            @foreach($nguoiThucHien as $nguoi)
                <option value="{{ $nguoi->id }}" @selected(request('nguoi') == $nguoi->id)>
                    {{ $nguoi->name }}
                </option>
            @endforeach
        </select>

        <input type="date" name="tu" value="{{ request('tu') }}"
               class="form-control" aria-label="Từ ngày">
        <input type="date" name="den" value="{{ request('den') }}"
               class="form-control" aria-label="Đến ngày">
    </x-admin.filter-bar>

    <div class="admin-panel">
        <div class="table-responsive">
            <table class="admin-table align-middle mb-0">

                <thead>
                    <tr>
                        <th style="width: 11rem;">Thời điểm</th>
                        <th style="width: 12rem;">Người thực hiện</th>
                        <th>Việc đã làm</th>
                        <th style="width: 9rem;">Địa chỉ IP</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">
                                <x-site.time :at="$log->created_at" />
                                <div class="admin-page-subtitle">
                                    <x-site.time :at="$log->created_at" relative />
                                </div>
                            </td>

                            <td>{{ $log->actorLabel() }}</td>

                            <td class="o-dai">
                                {{ $log->description }}

                                @if($log->properties)
                                    <details class="mt-1">
                                        <summary class="admin-page-subtitle" style="cursor: pointer;">
                                            Chi tiết
                                        </summary>
                                        <dl class="mb-0 mt-1 admin-page-subtitle">
                                            @foreach($log->properties as $khoa => $giaTri)
                                                <div class="d-flex gap-2">
                                                    <dt class="fw-normal">{{ $khoa }}:</dt>
                                                    <dd class="mb-0">
                                                        {{ is_scalar($giaTri) ? $giaTri : json_encode($giaTri, JSON_UNESCAPED_UNICODE) }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </details>
                                @endif
                            </td>

                            <td class="admin-page-subtitle text-nowrap">
                                {{ $log->ip_address ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-row
                            :colspan="4"
                            empty="Chưa có thao tác nào được ghi lại." />
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>

@endsection
