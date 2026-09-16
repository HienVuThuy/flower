{{-- BÁO CÁO BÀI / BÌNH LUẬN. --}}
@auth
    <div class="modal fade" id="hop-bao-cao" tabindex="-1" aria-labelledby="hop-bao-cao-tieu-de" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('shop.community.report') }}">
                    @csrf
                    <input type="hidden" name="loai" value="post" data-bao-cao-loai>
                    <input type="hidden" name="id" value="" data-bao-cao-id>

                    <div class="modal-header">
                        <h2 class="modal-title text-h4" id="hop-bao-cao-tieu-de">Báo cáo nội dung</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>

                    <div class="modal-body">
                        <p class="text-body-sm">Cửa hàng sẽ xem xét nội dung này. Chọn lý do gần nhất:</p>

                        @foreach(\App\Enums\CommunityReportReason::cases() as $i => $lyDo)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ly_do" id="ly-do-{{ $lyDo->value }}"
                                       value="{{ $lyDo->value }}" @checked($i === 0)>
                                <label class="form-check-label" for="ly-do-{{ $lyDo->value }}">{{ $lyDo->label() }}</label>
                            </div>
                        @endforeach

                        <div class="mt-3">
                            <label class="form-label" for="bao-cao-ghi-chu">Mô tả thêm (không bắt buộc)</label>
                            <textarea id="bao-cao-ghi-chu" name="ghi_chu" rows="2" maxlength="300" class="form-control"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Huỷ</button>
                        <button type="submit" class="btn btn-primary-brand">Gửi báo cáo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endauth
