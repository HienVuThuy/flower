@props(['product'])

@php
    /*
        Hiển thị đúng bộ thông tin chăm sóc của HÌNH THỨC BÁN.

        Guide mục 4.4: cây chậu cần ánh sáng/đất/phân bón..., còn bó hoa
        cần hướng dẫn giữ tươi. Trước đây component này in cứng 7 ô của
        cây chậu nên bó hoa không bao giờ hiện được thông tin của mình.

        Nhãn và biểu tượng lấy từ CareProfile — cùng một nguồn với form
        quản trị, không chép lại.
    */
    $profile = $product->careProfile();
    $entries = $product->careEntries();
    $fields = $profile->fields();

    $difficultyLabels = ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'];
@endphp

@if($entries)

    <div class="care-guide">

        @foreach($entries as $key => $value)
            @php $field = $fields[$key] ?? null; @endphp
            @continue(! $field)

            {{-- Ghi chú dài để riêng ở dưới, không nhét vào lưới ô nhỏ --}}
            @continue($field['input'] === 'textarea')

            <div class="care-guide__item">

                @if($field['icon'])
                    <span class="care-guide__icon"><x-site.icon :name="$field['icon']" /></span>
                @endif

                <span>
                    <span class="care-guide__label d-block">{{ $field['label'] }}</span>
                    <span class="care-guide__value">
                        {{ $field['input'] === 'difficulty'
                            ? ($difficultyLabels[$value] ?? $value)
                            : $value }}
                    </span>
                </span>

            </div>
        @endforeach

    </div>

    @if(! empty($entries['notes']))
        <p class="care-guide__notes">{{ $entries['notes'] }}</p>
    @endif

@endif
