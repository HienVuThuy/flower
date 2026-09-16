@props([
    'action',

    'viec',

    'canhBao' => [],

    'id' => 'bulk-form',
])

<form method="POST" action="{{ $action }}" id="{{ $id }}" data-bulk-form>
    @csrf

    <div class="admin-bulk" data-bulk-bar>

        <span class="admin-bulk__count" data-bulk-count>
            Tích chọn các dòng rồi chọn thao tác
        </span>

        <select name="viec" class="form-select form-select-sm w-auto" required
                aria-label="Chọn thao tác">
            <option value="">— Chọn thao tác —</option>
            @foreach($viec as $ma => $nhan)
                <option value="{{ $ma }}"
                        @isset($canhBao[$ma]) data-canh-bao="{{ $canhBao[$ma] }}" @endisset>
                    {{ $nhan }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-sm btn-primary-brand">Thực hiện</button>

        <button type="button" class="btn btn-sm btn-ghost" data-bulk-clear>Bỏ chọn</button>

    </div>
</form>
