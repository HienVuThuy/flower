@props(['journal'])

@php $tu = $journal->kind->entryWords(); @endphp

<div class="journal-panel">
    <div class="journal-panel__head">
        <h2 class="text-h3 mb-0">Dòng thời gian</h2>
        @if($journal->entries->isNotEmpty())
            <span class="journal-panel__meta">
                {{ $journal->entries->count() }} {{ $tu['one'] }}
            </span>
        @endif
    </div>

    @if($journal->entries->isEmpty())
        <x-site.empty-state
            :title="$tu['empty']"
            text="Điền vào biểu mẫu bên cạnh để bắt đầu." />
    @else
        @foreach($journal->entries as $entry)
            <div class="journal-entry">
                <div class="journal-entry__rail"></div>

                <div>
                    <div class="journal-entry__date">
                        {{ $entry->entry_date->format('d/m/Y') }}

                        @if($entry->condition)
                            · <span class="status-pill status-pill--{{ $entry->condition->badge() }}">
                                {{ $entry->condition->label() }}
                            </span>
                        @endif

                        @if($entry->field('rating'))
                            {{-- Điểm chấm của lần quan sát này (sổ Phân tích). --}}
                            · <span class="entry-rating" role="img"
                                    aria-label="Chấm {{ $entry->field('rating') }} trên 5">
                                @for($i = 1; $i <= 5; $i++)
                                    <x-site.icon :name="$i <= (int) $entry->field('rating') ? 'star-fill' : 'star'"
                                                 class="entry-rating__star" />
                                @endfor
                            </span>
                        @endif
                    </div>

                    <div class="journal-entry__title">
                        @if($entry->sticker)
                            {{-- Nhãn dán đứng TRƯỚC tiêu đề: nhìn lướt cả cột là
                                 thấy ngay chuyện gì đã xảy ra, không cần đọc chữ. --}}
                            <x-journal.sticker :sticker="$entry->sticker" :size="20"
                                               class="journal-entry__sticker" />
                        @endif

                        {{ $entry->displayTitle() }}
                    </div>

                    @if($entry->photo)
                        <x-site.image :path="$entry->photo"
                                      :alt="'Ảnh ngày ' . $entry->entry_date->format('d/m/Y')"
                                      class="journal-entry__photo" />
                    @endif

                    @if($entry->body)
                        <div class="journal-entry__body">{{ $entry->body }}</div>
                    @endif

                    @if($entry->field('good') || $entry->field('bad'))
                        <div class="entry-findings">
                            @if($entry->field('good'))
                                <p class="entry-findings__good mb-1"><strong>Được:</strong> {{ $entry->field('good') }}</p>
                            @endif
                            @if($entry->field('bad'))
                                <p class="entry-findings__bad mb-0"><strong>Chưa được:</strong> {{ $entry->field('bad') }}</p>
                            @endif
                        </div>
                    @endif

                    @if($entry->careActions())
                        <div class="journal-entry__care">
                            @foreach($entry->careActions() as $viec)
                                <span class="care-chip" title="{{ $viec->meaning() }}">
                                    <x-journal.sticker :sticker="$viec" :size="16" decorative />
                                    {{ $viec->label() }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if($entry->metrics->isNotEmpty())
                        <div class="journal-entry__metrics">
                            @foreach($entry->metrics as $metric)
                                <span class="journal-metric-chip">
                                    <span class="journal-metric-chip__name">{{ $metric->name }}</span>
                                    <span class="journal-metric-chip__value">{{ $metric->display() }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('shop.journals.entries.destroy', [$journal, $entry]) }}"
                          class="mt-2"
                          onsubmit="return confirm('Xoá {{ $tu['one'] }} ngày {{ $entry->entry_date->format('d/m/Y') }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">Xoá</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>
