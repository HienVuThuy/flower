@props(['journal'])

@php
    $tienDo = $journal->goalProgress();
@endphp

@if($journal->target_metric && $journal->target_value !== null)
    <div class="journal-panel journal-panel--goal">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <span>
                <strong>Mục tiêu:</strong>
                {{ $journal->target_metric }} đạt
                {{ rtrim(rtrim(number_format((float) $journal->target_value, 2, ',', '.'), '0'), ',') }}{{ $journal->target_unit ? ' ' . $journal->target_unit : '' }}
                @if($journal->target_date)
                    trước {{ $journal->target_date->format('d/m/Y') }}
                @endif
            </span>

            <span class="fw-bold">
                @if($tienDo === null)
                    <span class="text-body-sm">Chưa có số liệu cho chỉ số này</span>
                @else
                    {{ rtrim(rtrim(number_format($tienDo, 1, ',', '.'), '0'), ',') }}%
                @endif
            </span>
        </div>

        @if($tienDo !== null)
            <div class="goal-bar" role="progressbar"
                 aria-valuenow="{{ min(100, max(0, $tienDo)) }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Tiến độ mục tiêu">
                <div class="goal-bar__fill" style="width: {{ min(100, max(0, $tienDo)) }}%"></div>
            </div>

            @if($tienDo >= 100)
                <p class="text-body-sm mt-2 mb-0">Đã đạt mục tiêu.</p>
            @endif
        @endif
    </div>
@endif
