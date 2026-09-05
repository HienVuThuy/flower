@props(['journal'])

@php
    /*
     * ĐÃ CHĂM SÓC NHỮNG GÌ — tổng hợp việc đã làm.
     * ============================================================
     * Trả lời câu người trồng cây thật sự hỏi khi mở sổ ra: *lần gần nhất
     * mình bón phân là bao giờ?* Thông tin đó vốn đã nằm rải trong dòng
     * thời gian, nhưng muốn biết thì phải cuộn và đếm bằng mắt.
     *
     * KHÔNG NHẮC "ĐÃ ĐẾN LÚC TƯỚI CHƯA". Chu kỳ tưới phụ thuộc loài,
     * mùa, chậu, chỗ đặt và thời tiết tuần đó — hệ thống không biết gì
     * trong số đó. Đưa ra một lời nhắc dựa trên phép đếm ngày là bịa một
     * lời khuyên chăm cây, và người tin theo có thể làm úng cây.
     *
     * Nói *"đã tưới 6 lần, gần nhất 3 ngày trước"* là sự thật; nói *"nên
     * tưới hôm nay"* thì không.
     */
    $dem = $journal->careTally();

    // Lần gần nhất làm từng việc — quét một lượt qua các trang đã nạp sẵn,
    // không truy vấn thêm.
    $ganNhat = [];

    foreach ($journal->entries as $entry) {
        foreach ($entry->careActions() as $viec) {
            $cu = $ganNhat[$viec->value] ?? null;

            if (! $cu || $entry->entry_date->gt($cu)) {
                $ganNhat[$viec->value] = $entry->entry_date;
            }
        }
    }
@endphp

@if($dem->isNotEmpty())
    <div class="journal-panel">
        <div class="journal-panel__head">
            <h2 class="text-h3 mb-0">Đã chăm những gì</h2>
            <span class="journal-panel__meta">trong {{ $journal->entries->count() }} lần ghi</span>
        </div>

        <ul class="care-tally">
            @foreach($dem as $khoa => $soLan)
                @php
                    $viec = \App\Enums\JournalSticker::tryFrom((string) $khoa);
                    $ngay = $ganNhat[$khoa] ?? null;
                @endphp

                @if($viec)
                    <li class="care-tally__item">
                        <x-journal.sticker :sticker="$viec" :size="20" decorative />

                        <span class="care-tally__label">{{ $viec->label() }}</span>
                        <span class="care-tally__count">{{ $soLan }} lần</span>

                        @if($ngay)
                            <span class="care-tally__last">
                                @php $cach = (int) $ngay->startOfDay()->diffInDays(now()->startOfDay()); @endphp

                                @if($cach === 0)
                                    hôm nay
                                @elseif($cach === 1)
                                    hôm qua
                                @else
                                    {{ $cach }} ngày trước
                                @endif
                            </span>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@endif
