@extends('layouts.app')

@section('title', 'Chọn cây theo nhu cầu')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Chọn cây theo nhu cầu']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Tư vấn</span>
                <h1 class="text-h2 section-header__title">Bạn cần cây cho chỗ nào?</h1>
                <p class="mb-0">
                    Chọn điều kiện thật ở nhà bạn, chúng tôi lọc ra những cây sống được ở đó.
                </p>
            </div>
        </div>

        {{--
            BỘ LỌC LÀ LIÊN KẾT, KHÔNG PHẢI BIỂU MẪU.

            Mỗi lựa chọn là một URL riêng, nên khách gửi link cho nhau
            được, lưu vào dấu trang được, và nút Back của trình duyệt hoạt
            động đúng như mong đợi. Cũng không cần một dòng JavaScript nào.
        --}}
        <div class="advisor-filters">

            {{-- ---------- vị trí đặt ---------- --}}
            <div class="advisor-filters__group">
                <p class="advisor-filters__label">Đặt ở đâu</p>

                <div class="advisor-filters__options">
                    @foreach($placements as $row)
                        @php($p = $row['placement'])
                        <a href="{{ request()->fullUrlWithQuery(['vi-tri' => $placement === $p ? null : $p->value]) }}"
                           class="advisor-chip {{ $placement === $p ? 'is-active' : '' }}">
                            <x-site.icon :name="$p->icon()" class="advisor-chip__icon" />
                            <span class="advisor-chip__body">
                                <span class="advisor-chip__name">{{ $p->label() }}</span>
                                {{-- Điều kiện thật của chỗ đó, để khách tự
                                     đối chiếu với nhà mình thay vì đoán. --}}
                                <span class="advisor-chip__hint">{{ $p->hint() }}</span>
                            </span>
                            <span class="advisor-chip__count">{{ $row['total'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ---------- hợp mệnh ---------- --}}
            @if($elements->isNotEmpty())
                <div class="advisor-filters__group">
                    <p class="advisor-filters__label">
                        Hợp mệnh
                        {{--
                            NÓI RÕ ĐÂY LÀ TẬP QUÁN, KHÔNG PHẢI SỰ THẬT KHOA HỌC.

                            Rất nhiều khách mua cây cảnh ở Việt Nam hỏi câu
                            này, nên bỏ qua là bỏ mất một nhu cầu có thật.
                            Nhưng trình bày nó như một chỉ số kỹ thuật thì
                            thành ra cửa hàng khẳng định thay khách một
                            điều mình không có tư cách khẳng định.
                        --}}
                        <span class="advisor-filters__note">theo quan niệm phong thuỷ dân gian</span>
                    </p>

                    <div class="advisor-filters__options">
                        @foreach($elements as $row)
                            @php($e = $row['element'])
                            <a href="{{ request()->fullUrlWithQuery(['menh' => $element === $e ? null : $e->value]) }}"
                               class="advisor-chip {{ $element === $e ? 'is-active' : '' }}">
                                <span class="advisor-chip__body">
                                    <span class="advisor-chip__name">{{ $e->label() }}</span>
                                    <span class="advisor-chip__hint">{{ $e->colorHint() }}</span>
                                </span>
                                <span class="advisor-chip__count">{{ $row['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ---------- kinh nghiệm ---------- --}}
            @if($difficulties->isNotEmpty())
                <div class="advisor-filters__group">
                    <p class="advisor-filters__label">
                        Kinh nghiệm trồng cây
                        {{--
                            Ba mức thay cho một ô đánh dấu "tôi mới trồng cây".
                            Người đã trồng vài cây rồi cũng cần lọc — trước đây
                            họ chỉ có hai lựa chọn: tích ô (ra toàn cây quá dễ)
                            hoặc bỏ trống (ra tất cả, không lọc được gì).
                        --}}
                        <span class="advisor-filters__note">chọn mức bạn thấy đúng với mình</span>
                    </p>

                    <div class="advisor-filters__options">
                        @foreach($difficulties as $row)
                            @php($d = $row['difficulty'])
                            <a href="{{ request()->fullUrlWithQuery(['kinh-nghiem' => $difficulty === $d ? null : $d->value]) }}"
                               class="advisor-chip {{ $difficulty === $d ? 'is-active' : '' }}">
                                <span class="advisor-chip__body">
                                    <span class="advisor-chip__name">{{ $d->experienceLabel() }}</span>
                                    <span class="advisor-chip__hint">{{ $d->hint() }}</span>
                                </span>
                                <span class="advisor-chip__count">{{ $row['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{--
                ---------- TIÊU CHÍ SINH THÁI ----------
                Môi trường sống, dạng sống, dáng, màu sắc.

                MỘT VÒNG LẶP CHO CẢ BỐN. Ba khối bên trên (vị trí, mệnh,
                kinh nghiệm) viết riêng vì mỗi cái có một điểm đặc thù —
                vị trí có icon, mệnh có màu, kinh nghiệm có nhãn riêng.
                Bốn cái này thì giống hệt nhau, nên chép ra bốn khối là
                chép ba lần thừa, và lần sửa sau sẽ bỏ sót một khối.

                Controller đã lọc sẵn: nhóm nào không có hàng thì không
                lọt tới đây.
            --}}
            @foreach($nhomSinhThai as $nhom)
                <div class="advisor-filters__group">
                    <p class="advisor-filters__label">{{ $nhom['type']->label() }}</p>

                    <div class="advisor-filters__options">
                        @foreach($nhom['values'] as $row)
                            @php($dangChon = $nhom['dangChon'] === $row['value'])

                            {{-- Bấm lại vào lựa chọn đang chọn = bỏ chọn.
                                 Cùng cách hành xử với ba nhóm bên trên. --}}
                            <a href="{{ request()->fullUrlWithQuery([
                                   $nhom['type']->queryKey() => $dangChon ? null : $row['value'],
                               ]) }}"
                               class="advisor-chip {{ $dangChon ? 'is-active' : '' }}">
                                <span class="advisor-chip__body">
                                    <span class="advisor-chip__name">{{ $row['label'] }}</span>
                                    @if($row['hint'])
                                        {{-- Dịch một đặc điểm sinh học thành
                                             một việc phải làm khi chăm cây. --}}
                                        <span class="advisor-chip__hint">{{ $row['hint'] }}</span>
                                    @endif
                                </span>
                                <span class="advisor-chip__count">{{ $row['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{--
                LỐI SANG TRANG PHÂN LOẠI SINH HỌC.

                Trang này hỏi "bạn cần cây cho chỗ nào"; trang kia hỏi
                "cây này thuộc nhóm nào". Hai câu hỏi khác nhau, và người
                đã biết cây thường muốn câu thứ hai — nhưng họ sẽ không
                tìm ra nó nếu không có lối đi từ đây.
            --}}
            <p class="advisor-filters__note mt-2 mb-0">
                Biết tên loài rồi?
                <a href="{{ route('shop.taxa.index') }}">Duyệt theo phân loại sinh học</a>
            </p>

            @if($hasFilter)
                <a href="{{ route('shop.advisor.index') }}" class="btn btn-ghost btn-sm">Bỏ hết điều kiện</a>
            @endif

        </div>

        {{--
            ============ XEM KẾT QUẢ ============
            KẾT QUẢ HIỆN Ở TRANG SẢN PHẨM, KHÔNG HIỆN Ở ĐÂY.

            Trước đây trang này tự đổ kết quả ra, và sáu trong bảy tiêu chí
            của nó trùng với bộ lọc trang sản phẩm — hai bộ máy kết quả cho
            cùng một câu hỏi. Trang này lại thiếu hẳn sắp xếp, lọc giá và
            phân trang, nên khách trả lời xong vẫn phải sang trang kia để
            làm nốt.

            Nay bấm một nút là sang thẳng, mang theo đúng những gì đã chọn.
            Cái riêng của trang này — CÁCH HỎI theo ngôn ngữ người mua —
            giữ nguyên. Xem QĐ-172.
        --}}
        <div class="advisor-go mt-4">
            @if($hasFilter)
                <a href="{{ $ketQuaUrl }}" class="btn btn-primary-brand btn-lg">
                    Xem cây phù hợp
                </a>

                {{--
                    KHÔNG in trước số lượng ở đây.

                    Muốn có con số thì phải đếm bằng một câu truy vấn thứ
                    hai, và câu đó sẽ lệch với danh sách thật ngay khi
                    trang sản phẩm đổi cách lọc — đúng kiểu trùng lặp mà cả
                    thay đổi này sinh ra để bỏ. Con số thật nằm ở trang kết
                    quả, nơi nó đếm chính tập đang hiển thị.
                --}}
                <p class="advisor-go__note">
                    Sang trang sản phẩm với đúng điều kiện bạn vừa chọn —
                    ở đó lọc thêm được theo giá, và sắp xếp được.
                </p>
            @else
                <a href="{{ route('shop.products.index', ['kinh-nghiem' => \App\Enums\CareDifficulty::Easy->value]) }}"
                   class="btn btn-secondary-brand btn-lg">
                    Chưa biết chọn gì? Xem cây dễ chăm
                </a>

                <p class="advisor-go__note">
                    Hoặc chọn vài điều kiện ở trên rồi bấm xem kết quả.
                </p>
            @endif
        </div>

        <p class="text-caption mt-4 mb-0">
            Thông tin vị trí đặt và độ khó chăm dựa trên đặc tính trồng trọt của
            từng loại cây. Phần hợp mệnh là quan niệm phong thuỷ dân gian, cửa hàng
            ghi lại để bạn tham khảo chứ không khẳng định thay bạn.
        </p>

    </div>
</section>

@endsection
