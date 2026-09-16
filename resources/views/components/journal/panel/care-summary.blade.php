@props(['journal'])

@php
    $dem = $journal->careTally();

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
