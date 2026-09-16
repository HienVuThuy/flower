@props(['title' => 'Cam kết của cửa hàng'])

@php
    $items = \App\Services\Shop\ServiceCommitments::all();
@endphp

@if($items)
    <section class="commitments" aria-label="{{ $title }}">

        <h2 class="text-h4 commitments__title">{{ $title }}</h2>

        <ul class="commitments__list">
            @foreach($items as $item)
                <li class="commitments__item">
                    <span class="commitments__icon"><x-site.icon :name="$item['icon']" /></span>
                    <span>
                        <span class="commitments__label d-block">{{ $item['title'] }}</span>
                        @if($item['note'])
                            <span class="commitments__note">{{ $item['note'] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>

    </section>
@endif
