{{--
    Ô nhập của bài: dùng CHUNG cho hộp thoại đăng bài mới và trang sửa bài —
    hai bản sao thì sớm muộn lệch nhau một ô.

    `$post` là bài đang sửa, null khi đăng mới.
--}}
@php
    $baiSua = $post ?? null;
    $maO = $baiSua ? 'sua-' . $baiSua->id : 'moi';
    $kho = \App\Services\Community\CommunityMediaStore::class;
@endphp

<div class="mb-3">
    <label class="visually-hidden" for="body-{{ $maO }}">Bạn muốn kể gì?</label>
    <textarea id="body-{{ $maO }}" name="body" rows="4" maxlength="2000"
              class="form-control composer__text @error('body') is-invalid @enderror"
              placeholder="Cây monstera mua hồi tháng 6, giờ ra thêm bốn lá…">{{ old('body', $baiSua?->body) }}</textarea>
    <x-form-error name="body" />
</div>

@if($baiSua && $baiSua->media->isNotEmpty())
    <div class="mb-3">
        <span class="form-label d-block">Ảnh / video đang có</span>
        <div class="composer-media">
            @foreach($baiSua->media as $m)
                <label class="composer-media__item">
                    @if($m->laVideo())
                        <video class="composer-media__thumb" preload="metadata" muted src="{{ $m->url() }}#t=0.1"></video>
                    @else
                        <x-site.image :path="$m->path" alt="Ảnh của bài" class="composer-media__thumb" />
                    @endif
                    <span class="composer-media__xoa">
                        <input type="checkbox" name="xoa_media[]" value="{{ $m->id }}"> Xoá
                    </span>
                </label>
            @endforeach
        </div>
        <x-form-error name="xoa_media" :array="true" />
    </div>
@endif

<div class="mb-3">
    <label class="form-label" for="media-{{ $maO }}">Thêm ảnh / video</label>
    <input type="file" id="media-{{ $maO }}" name="media[]" multiple data-media-input
           class="form-control @error('media') is-invalid @enderror"
           accept="image/png,image/jpeg,image/webp,video/mp4,video/webm">
    <div class="form-text">
        Tối đa {{ $kho::TOI_DA_TEP }} tệp mỗi bài, trong đó tối đa {{ $kho::TOI_DA_VIDEO }} video.
        Ảnh ≤ {{ intdiv($kho::ANH_TOI_DA_KB, 1024) }}MB (JPG, PNG, WebP);
        video ≤ {{ intdiv($kho::VIDEO_TOI_DA_KB, 1024) }}MB (MP4, WebM).
    </div>
    <div class="composer-media" data-media-preview hidden></div>
    <p class="composer__canhbao" data-media-canhbao hidden></p>
    <x-form-error name="media" />
    <x-form-error name="media.*" :array="true" />
</div>

{{--
    NÓI THẲNG VỀ VIỆC TƯỚC METADATA: khách không biết ảnh và video quay bằng
    điện thoại mang theo toạ độ GPS. Hệ thống tự xoá là đúng, nhưng nói ra thì
    họ yên tâm đăng — và người đã biết thì không phải tự hỏi.
--}}
<p class="composer__privacy">
    Ảnh và video bạn tải lên được <strong>tự động xoá thông tin ẩn</strong>
    (vị trí GPS, loại máy, giờ chụp) trước khi lưu.
</p>

@if($cayDaMua->isNotEmpty())
    <div class="mb-3">
        <label class="form-label" for="product-{{ $maO }}">Cây trong bài</label>
        <select name="product_id" id="product-{{ $maO }}" class="form-select">
            <option value="">— Không gắn —</option>
            @foreach($cayDaMua as $p)
                <option value="{{ $p->id }}" @selected((int) old('product_id', $baiSua?->product_id) === $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        {{-- Chỉ cây đã mua — cùng luật với nhật ký (QĐ-129). --}}
        <p class="form-text">Chỉ liệt kê cây bạn đã mua ở cửa hàng.</p>
        <x-form-error name="product_id" />
    </div>
@endif
