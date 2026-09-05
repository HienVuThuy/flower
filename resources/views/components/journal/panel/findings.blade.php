@props(['journal'])

@php
    /*
     * ƯU VÀ NHƯỢC GOM LẠI — khối của sổ Phân tích.
     * ============================================================
     * Người ghi sổ phân tích viết "ưu" và "nhược" rải qua nhiều lần quan
     * sát, cách nhau hàng tuần. Gom lại hai cột thì đọc một lượt là ra
     * kết luận — thứ mà cuộn dòng thời gian không cho được.
     *
     * GIỮ NGÀY BÊN CẠNH TỪNG Ý. Một nhược điểm ghi hồi tháng trước có thể
     * đã tự hết; bỏ ngày đi thì cả hai cột trông như đang cùng đúng ở
     * hiện tại.
     */
    $uu = collect();
    $nhuoc = collect();

    foreach ($journal->entries as $entry) {
        if ($t = trim((string) $entry->field('good'))) {
            $uu->push(['text' => $t, 'date' => $entry->entry_date]);
        }

        if ($t = trim((string) $entry->field('bad'))) {
            $nhuoc->push(['text' => $t, 'date' => $entry->entry_date]);
        }
    }
@endphp

@if($uu->isNotEmpty() || $nhuoc->isNotEmpty())
    <div class="journal-panel">
        <div class="journal-panel__head">
            <h2 class="text-h3 mb-0">Đã rút ra được gì</h2>
        </div>

        <div class="findings">
            <div class="findings__col findings__col--good">
                <h3 class="findings__title">Được</h3>

                @if($uu->isEmpty())
                    <p class="text-body-sm mb-0">Chưa ghi ý nào.</p>
                @else
                    <ul class="findings__list">
                        @foreach($uu as $y)
                            <li>
                                {{ $y['text'] }}
                                <span class="findings__date">{{ $y['date']->format('d/m') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="findings__col findings__col--bad">
                <h3 class="findings__title">Chưa được</h3>

                @if($nhuoc->isEmpty())
                    <p class="text-body-sm mb-0">Chưa ghi ý nào.</p>
                @else
                    <ul class="findings__list">
                        @foreach($nhuoc as $y)
                            <li>
                                {{ $y['text'] }}
                                <span class="findings__date">{{ $y['date']->format('d/m') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endif
