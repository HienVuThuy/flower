{{--
    MỘT KHỐI trong phần mô tả chi tiết.

    Biến: $i (chỉ số dòng, hoặc '__INDEX__' cho mẫu JavaScript), $khoi (mảng).

    Chỉ số nằm trong tên trường (`blocks[0][body]`) chứ không phải một ô "thứ
    tự" riêng: thứ tự hiện ra là thứ tự các dòng gửi lên, xem ProductBlockService.
--}}
@php
    $kind = $khoi['kind'] ?? 'text';
    $laAnh = $kind === 'image';
@endphp

<div class="block-row" data-block-row>

    <input type="hidden" name="blocks[{{ $i }}][kind]" value="{{ $kind }}">

    @if(! empty($khoi['id']))
        {{-- Giữ id để sửa đúng khối cũ thay vì xoá rồi tạo lại (tạo lại là mất
             tệp ảnh đang dùng). --}}
        <input type="hidden" name="blocks[{{ $i }}][id]" value="{{ $khoi['id'] }}">
    @endif

    <div class="block-row__head">
        <span class="block-row__kind">
            <x-site.icon :name="$laAnh ? 'flower2' : 'journal'" />
            {{ $laAnh ? 'Ảnh' : 'Chữ' }}
        </span>

        <span class="block-row__tools">
            {{-- Đổi thứ tự bằng hai nút, không kéo thả: kéo thả không dùng được
                 bằng bàn phím, và trên điện thoại thì tranh chấp với cuộn trang. --}}
            <button type="button" class="btn btn-ghost btn-sm" data-block-up aria-label="Đưa khối lên trên">↑</button>
            <button type="button" class="btn btn-ghost btn-sm" data-block-down aria-label="Đưa khối xuống dưới">↓</button>
            <button type="button" class="btn btn-ghost btn-sm text-danger" data-block-remove aria-label="Xoá khối">✕</button>
        </span>
    </div>

    @if($laAnh)
        @if(! empty($khoi['image_path']))
            <img src="{{ asset('storage/' . $khoi['image_path']) }}" alt="" class="block-row__preview">
            <div class="form-text mb-2">Chọn ảnh mới để thay ảnh này.</div>
        @endif

        <input type="file" name="blocks[{{ $i }}][image]" accept=".jpg,.jpeg,.png,.webp"
               class="form-control form-control-sm mb-2" aria-label="Ảnh của khối">

        <input type="text" name="blocks[{{ $i }}][caption]" maxlength="255"
               value="{{ $khoi['caption'] ?? '' }}"
               class="form-control form-control-sm" placeholder="Chú thích dưới ảnh (không bắt buộc)"
               aria-label="Chú thích ảnh">
    @else
        <textarea name="blocks[{{ $i }}][body]" rows="4" maxlength="5000"
                  class="form-control form-control-sm"
                  placeholder="Một đoạn mô tả…"
                  aria-label="Nội dung khối chữ">{{ $khoi['body'] ?? '' }}</textarea>
    @endif

</div>
