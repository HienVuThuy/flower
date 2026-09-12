@props([
    // Các mốc, cũ trước mới sau — dùng $order->statusEvents.
    'events',

    /*
     * Hiện tên người thực hiện hay không.
     *
     * MẶC ĐỊNH LÀ KHÔNG, và đó là chủ ý: với khách hàng, người bấm nút
     * là "cửa hàng", không phải một cái tên. Hiện tên nhân viên trên
     * trang công khai là lộ thông tin nội bộ mà không đem lại gì cho
     * người đọc — họ không liên hệ được với người đó, và cũng không cần.
     *
     * Trang quản trị bật lên, vì ở đó "ai làm" chính là câu hỏi.
     */
    'showActor' => false,
])

{{--
    DÒNG THỜI GIAN CỦA ĐƠN HÀNG.
    ============================================================
    MỘT COMPONENT CHO CẢ HAI TRANG (khách và quản trị), khác nhau đúng
    một điều: có hiện tên người thực hiện hay không. Viết hai bản thì
    hai bản sẽ lệch nhau — mà lệch ở đây nghĩa là khách và nhân viên
    nhìn thấy hai lịch sử khác nhau cho cùng một đơn, và không ai biết
    bản nào đúng.

    HIỆN MỐC ĐÃ XẢY RA, KHÔNG VẼ SẴN CÁC BƯỚC CHƯA TỚI.

    Nhiều nơi vẽ đủ năm bước rồi tô mờ những bước chưa đến. Ở đây không
    làm vậy vì đơn CÓ THỂ BỊ HUỶ ở bất cứ bước nào — vẽ sẵn "Đang giao"
    và "Đã giao" cho một đơn vừa huỷ là hứa một việc sẽ không xảy ra.
--}}

@if($events->isEmpty())
    <p class="admin-page-subtitle mb-0">Chưa có mốc nào được ghi cho đơn này.</p>
@else
    <ol class="order-timeline">
        @foreach($events as $event)
            <li class="order-timeline__item order-timeline__item--{{ $event->status->badge() }}">

                <div class="order-timeline__dot" aria-hidden="true"></div>

                <div class="order-timeline__body">
                    <div class="order-timeline__title">
                        {{ $event->status->label() }}
                    </div>

                    <div class="order-timeline__time">
                        <x-site.time :at="$event->created_at" />
                        @if($showActor)
                            — {{ $event->actorLabel() }}
                        @endif
                    </div>

                    @if($event->note)
                        {{--
                            Ghi chú ở đây KHÁCH ĐỌC ĐƯỢC (lý do huỷ chẳng
                            hạn). Ghi chú nội bộ nằm ở orders.admin_note,
                            không bao giờ đi qua chỗ này.
                        --}}
                        <div class="order-timeline__note">{{ $event->note }}</div>
                    @endif
                </div>

            </li>
        @endforeach
    </ol>
@endif
