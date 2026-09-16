{{-- HỘP THOẠI ĐĂNG BÀI. --}}
<div class="modal fade" id="hop-dang-bai" tabindex="-1" aria-labelledby="hop-dang-bai-tieu-de" aria-hidden="true"
     @if($errors->any() && old('_form') === 'dang-bai') data-mo-lai @endif>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('shop.community.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_form" value="dang-bai">

                <div class="modal-header">
                    <h2 class="modal-title text-h4" id="hop-dang-bai-tieu-de">Khoe cây của bạn</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>

                <div class="modal-body">
                    @include('shop.community.partials.composer-fields', ['post' => null])

                    <p class="text-caption mb-0">Bài được cửa hàng duyệt trước khi hiện — thường trong ngày.</p>
                </div>

                <div class="modal-footer justify-content-between">
                    <x-community.emoji-picker target="#body-moi" />
                    <button type="submit" class="btn btn-primary-brand">Đăng bài</button>
                </div>
            </form>
        </div>
    </div>
</div>
