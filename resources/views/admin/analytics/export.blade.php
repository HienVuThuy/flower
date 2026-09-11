@extends('layouts.admin')

@section('title', 'Xuất dữ liệu')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Xuất dữ liệu phân tích</h1>
    <p class="admin-page-subtitle">
        Chọn đúng những phần cần và định dạng phù hợp với việc bạn sắp làm.
        Mọi con số đếm trực tiếp từ cơ sở dữ liệu tại thời điểm bấm tải.
    </p>
</div>

{{--
    GET chứ không POST.

    Đây là một thao tác ĐỌC: nó không đổi gì trong hệ thống. Dùng GET thì
    đường dẫn kết quả chép và lưu dấu trang được — "báo cáo doanh thu 30
    ngày, dạng CSV" thành một liên kết gửi cho kế toán mỗi tháng.
--}}
<form method="GET" action="{{ route('admin.analytics.export') }}">

    <div class="row g-3">

        <div class="col-lg-8">
            <div class="admin-panel p-4 h-100">

                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
                    <h2 class="h6 fw-bold mb-0">Chọn phần muốn xuất</h2>

                    {{-- Không có JavaScript thì hai nút này không hiện —
                         xem CSS `html:not(.has-js)`. Bày một nút bấm vào
                         không có gì xảy ra còn tệ hơn không có nút. --}}
                    <div class="export-pick-all d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-admin" data-pick-all>Chọn tất cả</button>
                        <button type="button" class="btn btn-sm btn-outline-admin" data-pick-none>Bỏ chọn</button>
                    </div>
                </div>

                @php
                    /*
                     * Gom theo nhóm để danh sách không đọc ra như một dãy
                     * mười một ô đánh dấu không liên quan gì tới nhau.
                     *
                     * preserveKeys: BẮT BUỘC. Không có nó, groupBy() đánh
                     * số lại từ 0 và mã phần biến mất — mọi ô đánh dấu gửi
                     * lên value="0", "1", "2"... Controller lọc qua danh
                     * sách hợp lệ nên tất cả bị bỏ, rơi vào nhánh "không
                     * chọn gì thì xuất tất cả", và bộ chọn phần trông vẫn
                     * bình thường trong khi nó KHÔNG hề hoạt động.
                     */
                    $theoNhom = collect($sections)->groupBy('group', preserveKeys: true);
                @endphp

                @foreach($theoNhom as $tenNhom => $muc)
                    <h3 class="admin-section-title">{{ $tenNhom }}</h3>

                    <div class="export-list mb-3">
                        @foreach($muc as $ma => $m)
                            <label class="export-item">
                                {{-- Tích sẵn TẤT CẢ: người vào đây thường
                                     muốn cả bộ, và ai chỉ cần một phần thì
                                     bỏ tích nhanh hơn là tích từng cái. --}}
                                <input type="checkbox" class="form-check-input" name="phan[]"
                                       value="{{ $ma }}" checked data-pick>

                                <span class="export-item__body">
                                    <span class="export-item__name">{{ $m['label'] }}</span>
                                    <span class="export-item__note">{{ $m['note'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endforeach

            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel p-4 h-100">

                <h2 class="h6 fw-bold mb-3">Khoảng thời gian</h2>

                <div class="d-flex flex-column gap-2 mb-4">
                    @foreach($periods as $value => $label)
                        <label class="export-item">
                            <input type="radio" class="form-check-input" name="ky"
                                   value="{{ $value }}" @checked($period === (string) $value)>
                            <span class="export-item__body">
                                <span class="export-item__name">{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <h2 class="h6 fw-bold mb-3">Định dạng</h2>

                <div class="d-flex flex-column gap-2 mb-4">
                    @foreach($formats as $ma => $mo)
                        <label class="export-item">
                            <input type="radio" class="form-check-input" name="dinh_dang"
                                   value="{{ $ma }}" @checked($ma === 'csv')>
                            <span class="export-item__body">
                                <span class="export-item__name">{{ strtoupper($ma) }}</span>
                                <span class="export-item__note">{{ \Illuminate\Support\Str::after($mo, '— ') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                {{--
                    NÓI THẲNG RA CÁI KHÔNG CÓ.

                    Người dùng sẽ đi tìm nút "Excel" và "PDF". Im lặng thì
                    họ tưởng trang thiếu tính năng; nói ra thì họ biết
                    đường làm và biết vì sao.
                --}}
                <p class="admin-page-subtitle mb-3">
                    Cần tệp Excel thì mở tệp CSV bằng Excel — không cần bước nào khác.
                    Cần PDF thì chọn HTML rồi bấm In &rarr; Lưu thành PDF.
                </p>

                <button type="submit" class="btn btn-primary-brand w-100">Tải về</button>

            </div>
        </div>

    </div>

</form>

@endsection
