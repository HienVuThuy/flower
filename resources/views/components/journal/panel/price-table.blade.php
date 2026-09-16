@props(['journal'])

@php
    $tk = $journal->priceStats();
    $thapNhat = $tk['low'] ?? null;
@endphp

<div class="journal-panel">
    <div class="journal-panel__head">
        <h2 class="text-h3 mb-0">Các lần khảo giá</h2>
    </div>

    @if($journal->entries->isEmpty())
        <x-site.empty-state
            title="{{ $journal->kind->entryWords()['empty'] }}"
            text="Điền vào biểu mẫu bên cạnh để ghi lần khảo giá đầu tiên." />
    @else
        <div class="table-responsive">
            <table class="table journal-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Ngày</th>
                        <th scope="col">Giá</th>
                        <th scope="col">Khảo ở đâu</th>
                        <th scope="col">Ghi chú</th>
                        <th scope="col"><span class="visually-hidden">Xoá</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($journal->entries as $entry)
                        @php $gia = $entry->field('price'); @endphp

                        <tr @class(['is-lowest' => $thapNhat !== null && is_numeric($gia) && (float) $gia <= $thapNhat])>
                            <td>{{ $entry->entry_date->format('d/m/Y') }}</td>

                            <td class="journal-table__price">
                                @if(is_numeric($gia))
                                    <x-site.money :amount="$gia" />
                                    @if($thapNhat !== null && (float) $gia <= $thapNhat)
                                        <span class="badge-lowest">thấp nhất</span>
                                    @endif
                                @else
                                    <span class="text-body-sm">—</span>
                                @endif
                            </td>

                            <td>{{ $entry->field('place') ?: '—' }}</td>

                            <td>
                                @if($entry->sticker)
                                    <x-journal.sticker :sticker="$entry->sticker" :size="16" />
                                @endif
                                {{ $entry->body ?: '' }}
                            </td>

                            <td class="text-end">
                                <form method="POST"
                                      action="{{ route('shop.journals.entries.destroy', [$journal, $entry]) }}"
                                      onsubmit="return confirm('Xoá lần khảo giá ngày {{ $entry->entry_date->format('d/m/Y') }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm"
                                            aria-label="Xoá lần khảo ngày {{ $entry->entry_date->format('d/m/Y') }}">×</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
