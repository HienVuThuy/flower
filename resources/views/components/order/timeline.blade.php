@props([
    'events',

    'showActor' => false,
])

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
                        <div class="order-timeline__note">{{ $event->note }}</div>
                    @endif
                </div>

            </li>
        @endforeach
    </ol>
@endif
