@props([
    'name' => 'province',
    'id' => null,
    'selected' => '',
    'required' => true,
])

@php
    $inputId = $id ?? $name;
    $current = (string) old($name, $selected);
    $groups = \App\Services\Shop\Provinces::grouped();

    /*
     * Giá trị đang lưu KHÔNG còn trong danh sách hiện hành.
     *
     * Xảy ra với địa chỉ nhập từ trước đợt sáp nhập 2025 (ví dụ "Bình
     * Dương"). Nếu chỉ in ra 34 lựa chọn thì <select> tự nhảy về mục
     * đầu, và khách bấm Lưu là địa chỉ bị đổi sang tỉnh khác mà không hề
     * hay biết. Ở đây giữ lại giá trị cũ thành một mục riêng, có ghi chú.
     */
    $isLegacy = $current !== '' && ! \App\Services\Shop\Provinces::isValid($current);
@endphp

<select
    name="{{ $name }}"
    id="{{ $inputId }}"
    class="form-select {{ $errors->has($name) ? 'is-invalid' : '' }}"
    @if($required) required @endif
    {{ $attributes }}
>
    <option value="">-- Chọn tỉnh/thành phố --</option>

    @if($isLegacy)
        <optgroup label="Giá trị đang lưu (không còn trong danh sách hiện hành)">
            <option value="{{ $current }}" selected>{{ $current }}</option>
        </optgroup>
    @endif

    @foreach($groups as $groupLabel => $items)
        <optgroup label="{{ $groupLabel }}">
            @foreach($items as $item)
                <option value="{{ $item }}" @selected($current === $item)>{{ $item }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
