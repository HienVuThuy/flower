@props(['journal'])

@php
    /*
     * CÁC MỐC CẦN ĐẠT — khối làm nên sổ Mục tiêu.
     * ============================================================
     * Trước khi có khối này, sổ "Mục tiêu" chỉ khác sổ sinh trưởng ở chỗ
     * có một thanh tiến độ. Mà một mục tiêu thật thì hiếm khi là một con
     * số duy nhất: "nhân giống được 5 chậu trầu bà" gồm giâm cành, ra rễ,
     * trồng chậu, sống qua tháng đầu — bốn việc, mỗi việc một hạn.
     */
    $tienDo = $journal->milestoneProgress();
@endphp

<div class="journal-panel">
    <div class="journal-panel__head">
        <h2 class="text-h3 mb-0">Các mốc cần đạt</h2>

        @if($tienDo)
            <span class="journal-panel__meta">
                {{ $tienDo['done'] }}/{{ $tienDo['total'] }} mốc
            </span>
        @endif
    </div>

    @if($tienDo)
        <div class="goal-bar mb-3" role="progressbar"
             aria-valuenow="{{ $tienDo['percent'] }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="Tiến độ các mốc">
            <div class="goal-bar__fill" style="width: {{ $tienDo['percent'] }}%"></div>
        </div>
    @endif

    @if($journal->milestones->isEmpty())
        {{--
            CHƯA CÓ MỐC NÀO thì nói đúng như vậy, không hiện thanh 0%.
            Cùng nguyên tắc với `goalProgress()` — xem QĐ-127.
        --}}
        <p class="text-body-sm">
            Chưa đặt mốc nào. Chia mục tiêu thành vài bước nhỏ thì mỗi lần mở sổ ra
            là biết ngay mình đang ở đâu.
        </p>
    @else
        <ul class="milestone-list">
            @foreach($journal->milestones as $moc)
                <li class="milestone {{ $moc->isDone() ? 'is-done' : '' }} {{ $moc->isOverdue() ? 'is-overdue' : '' }}">
                    {{--
                        MỘT NÚT, KHÔNG PHẢI Ô TÍCH TỰ GỬI.

                        Ô tích cần JavaScript để gửi đi; không có script thì
                        nó tích được mà không lưu được — tệ hơn là không có ô
                        nào, vì người dùng tưởng đã xong.

                        Một form với nút bấm thì chạy ở mọi nơi.
                    --}}
                    <form method="POST"
                          action="{{ route('shop.journals.milestones.toggle', [$journal, $moc]) }}"
                          class="milestone__toggle-form">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="milestone__toggle"
                                aria-pressed="{{ $moc->isDone() ? 'true' : 'false' }}"
                                title="{{ $moc->isDone() ? 'Bỏ đánh dấu hoàn thành' : 'Đánh dấu đã xong' }}">
                            <span class="milestone__box" aria-hidden="true">
                                @if($moc->isDone())
                                    <svg viewBox="0 0 16 16" width="12" height="12" fill="none"
                                         stroke="currentColor" stroke-width="2.4"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m3 8.5 3.2 3.2L13 4.8" />
                                    </svg>
                                @endif
                            </span>
                            <span class="visually-hidden">
                                {{ $moc->isDone() ? 'Bỏ đánh dấu' : 'Đánh dấu đã xong' }}: {{ $moc->title }}
                            </span>
                        </button>
                    </form>

                    <div class="milestone__body">
                        <span class="milestone__title">{{ $moc->title }}</span>

                        <span class="milestone__meta">
                            @if($moc->isDone())
                                {{-- NGÀY XONG, không chỉ là "đã xong": đó là thứ
                                     dựng được câu "mất ba tuần cho bước này". --}}
                                Xong {{ $moc->done_at->format('d/m/Y') }}
                            @elseif($moc->due_date)
                                @if($moc->isOverdue())
                                    Quá hạn {{ $moc->due_date->format('d/m/Y') }}
                                @else
                                    Hạn {{ $moc->due_date->format('d/m/Y') }}
                                @endif
                            @endif
                        </span>
                    </div>

                    <form method="POST"
                          action="{{ route('shop.journals.milestones.destroy', [$journal, $moc]) }}"
                          onsubmit="return confirm('Xoá mốc &quot;{{ $moc->title }}&quot;?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm milestone__remove"
                                aria-label="Xoá mốc {{ $moc->title }}">×</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('shop.journals.milestones.store', $journal) }}"
          class="milestone-add">
        @csrf

        <input type="text" name="title" class="form-control form-control-sm"
               maxlength="150" required
               placeholder="Thêm một mốc, ví dụ: giâm cành ra rễ"
               aria-label="Tên mốc mới">

        <input type="date" name="due_date" class="form-control form-control-sm"
               aria-label="Hạn cho mốc này (không bắt buộc)">

        <button type="submit" class="btn btn-ghost btn-sm">Thêm mốc</button>
    </form>
    <x-form-error name="title"/>
</div>
