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
