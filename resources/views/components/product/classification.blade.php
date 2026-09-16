@props(['product'])

@php
    use App\Enums\TraitType;

    $nhomNhan = collect([
        TraitType::GrowthForm,
        TraitType::Habitat,
        TraitType::Shape,
        TraitType::Color,
    ])
        ->map(fn (TraitType $type) => ['type' => $type, 'values' => $product->traitValues($type)])
        ->filter(fn (array $n) => $n['values'] !== []);

    $taxon = $product->taxon;

    $goiY = collect($product->traitValues(TraitType::Habitat))
        ->map(fn ($v) => \App\Enums\Habitat::tryFrom($v)?->hint())
        ->filter()
        ->unique();
@endphp

@if($nhomNhan->isNotEmpty() || $taxon)

    <section class="classify">

        <h2 class="text-h3 classify__title">Phân loại &amp; đặc điểm</h2>

        <div class="classify__grid">

            @if($taxon)
                <div class="classify__taxonomy">
                    <p class="classify__sub">Phân loại khoa học</p>

                    <ol class="taxon-chain">
                        @foreach($taxon->chain() as $nut)
                            <li>
                                <a href="{{ route('shop.taxa.show', $nut) }}" class="taxon-chain__item">
                                    <span class="taxon-chain__rank">{{ $nut->rank->label() }}</span>
                                    <span class="taxon-chain__name">
                                        {{ $nut->name }}
                                        @if($nut->scientific_name)
                                            @if($nut->rank->italic())
                                                <em class="taxon-chain__sci">{{ $nut->scientific_name }}</em>
                                            @else
                                                <span class="taxon-chain__sci">{{ $nut->scientific_name }}</span>
                                            @endif
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ol>

                    @if($product->taxon_note)
                        <p class="classify__note">
                            <x-site.icon name="info-circle" />
                            {{ $product->taxon_note }}
                        </p>
                    @endif
                </div>
            @endif

            @if($nhomNhan->isNotEmpty())
                <div class="classify__traits">
                    <p class="classify__sub">Đặc điểm</p>

                    <div class="classify__cards">
                        @foreach($nhomNhan as $nhom)
                            <div class="classify__card">
                                <span class="classify__card-label">{{ $nhom['type']->label() }}</span>

                                <span class="classify__card-values">
                                    @foreach($nhom['values'] as $value)
                                        <a href="{{ route('shop.products.index', [$nhom['type']->queryKey() => $value]) }}"
                                           class="filter-chip filter-chip--sm">
                                            @if($nhom['type'] === TraitType::Color)
                                                <span class="filter-chip__dot"
                                                      @if($hex = \App\Enums\PlantColor::tryFrom($value)?->hex())
                                                          style="background: {{ $hex }}"
                                                      @else
                                                          style="background: linear-gradient(135deg,#ec8ba7,#f2c229,#4c8b5b)"
                                                      @endif
                                                      aria-hidden="true"></span>
                                            @endif
                                            {{ $nhom['type']->labelFor($value) }}
                                        </a>
                                    @endforeach
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @if($goiY->isNotEmpty())
                        <p class="classify__note">
                            <x-site.icon name="info-circle" />
                            {{ $goiY->implode('. ') }}.
                        </p>
                    @endif
                </div>
            @endif

        </div>
    </section>

@endif
