@props(['journal'])

@php
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
