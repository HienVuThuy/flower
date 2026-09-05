@props(['journal'])

@php
    /*
     * DẢI ẢNH THEO THỜI GIAN — khối đáng giá nhất của sổ sinh trưởng.
     * ============================================================
     * Ảnh vốn đã nằm trong dòng thời gian, nhưng ở đó chúng cách nhau vài
     * màn hình cuộn. Đặt cạnh nhau theo hàng ngang thì thấy được cái mà
     * không con số nào nói ra: cây đã lớn thế nào.
     *
     * XẾP CŨ TRƯỚC MỚI SAU, ngược với dòng thời gian. Lớn lên thì đọc từ
     * trái sang phải; đưa thứ tự mới-trước vào thì cây trông như đang teo
     * lại.
     */
    $anh = $journal->photoStrip();
@endphp

@if($anh->count() >= 2)
    <div class="journal-panel">
        <div class="journal-panel__head">
            <h2 class="text-h3 mb-0">Ảnh theo thời gian</h2>
            <span class="journal-panel__meta">
                {{ $anh->first()->entry_date->format('d/m/Y') }}
                → {{ $anh->last()->entry_date->format('d/m/Y') }}
            </span>
        </div>

        <p class="text-body-sm">
            Chụp cùng một góc mỗi lần thì dải ảnh này đọc được như một đoạn phim.
        </p>

        {{-- Cuộn ngang trong khung của chính nó — thân trang không bao
             giờ được cuộn ngang. --}}
        <div class="photo-strip">
            @foreach($anh as $entry)
                <figure class="photo-strip__item">
                    <x-site.image :path="$entry->photo"
                                  :alt="'Ảnh ngày ' . $entry->entry_date->format('d/m/Y')"
                                  class="photo-strip__img" />
                    <figcaption class="photo-strip__date">
                        {{ $entry->entry_date->format('d/m/Y') }}
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
@elseif($anh->count() === 1)
    {{--
        MỘT ẢNH THÌ KHÔNG CÓ "THEO THỜI GIAN" NÀO ĐỂ NHÌN.

        Cùng nguyên tắc với biểu đồ một điểm (QĐ-127): bày một dải ảnh
        gồm đúng một ảnh là hứa một thứ chưa có. Nhưng vẫn nói cho người
        dùng biết còn thiếu gì để có nó.
    --}}
    <div class="journal-panel">
        <div class="journal-panel__head">
            <h2 class="text-h3 mb-0">Ảnh theo thời gian</h2>
        </div>
        <p class="text-body-sm mb-0">
            Mới có một ảnh. Thêm một lần chụp nữa — cùng góc, cùng khoảng cách —
            là bắt đầu thấy được cây thay đổi thế nào.
        </p>
    </div>
@endif
