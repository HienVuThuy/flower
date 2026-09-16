@props(['product'])

@php
    $profile = $product->careProfile();
    $entries = $product->careEntries();
    $fields = $profile->fields();

    $difficultyLabels = ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'];
@endphp

@if($entries)

    <div class="care-guide">

        @foreach($entries as $key => $value)
            @php $field = $fields[$key] ?? null; @endphp
            @continue(! $field)

            @continue($field['input'] === 'textarea')

            <div class="care-guide__item">

                @if($field['icon'])
                    <span class="care-guide__icon"><x-site.icon :name="$field['icon']" /></span>
                @endif

                <span>
                    <span class="care-guide__label d-block">{{ $field['label'] }}</span>
                    <span class="care-guide__value">
                        {{ $field['input'] === 'difficulty'
                            ? ($difficultyLabels[$value] ?? $value)
                            : $value }}
                    </span>
                </span>

            </div>
        @endforeach

    </div>

    @if(! empty($entries['notes']))
        <p class="care-guide__notes">{{ $entries['notes'] }}</p>
    @endif

@endif
