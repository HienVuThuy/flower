@props(['product'])

@php
    use App\Enums\TraitType;

    /*
     * Chỉ những loại nhãn SẢN PHẨM NÀY THẬT SỰ CÓ.
     *
     * Hiện đủ bốn dòng với vài dòng bỏ trống là bày ra một bảng thiếu
     * dữ liệu. Bó hoa cưới không có "môi trường sống" — đó không phải
     * dữ liệu thiếu, mà là câu hỏi không áp dụng cho mặt hàng đó.
     */
    $nhomNhan = collect([
        TraitType::GrowthForm,
        TraitType::Habitat,
        TraitType::Shape,
        TraitType::Color,
    ])
        ->map(fn (TraitType $type) => ['type' => $type, 'values' => $product->traitValues($type)])
        ->filter(fn (array $n) => $n['values'] !== []);

    $taxon = $product->taxon;

    /*
     * Cách chăm suy ra từ môi trường sống.
     *
     * Nhãn "Sa mạc, khô hạn" là kiến thức; "tưới thưa, úng nước là chết"
     * là việc phải làm. Suy ra được nên không phải nhập tay từng món.
     */
    $goiY = collect($product->traitValues(TraitType::Habitat))
        ->map(fn ($v) => \App\Enums\Habitat::tryFrom($v)?->hint())
        ->filter()
        ->unique();
@endphp

@if($nhomNhan->isNotEmpty() || $taxon)

    {{--
        KHỐI CHIẾM TRỌN CHIỀU NGANG, chia hai cột bên trong.
        ============================================================
        BẢN TRƯỚC LÀ MỘT `col-lg-6` NỐI ĐUÔI hàng bên trên, nên nó rơi
        xuống dòng mới và chiếm đúng nửa trái — bỏ trống hẳn nửa phải
        màn hình. Khối "Chăm sóc cây" ngay trên thì dàn ba cột icon, còn
        khối này thì một cột chữ dài ngoằng: hai khối cạnh nhau mà nhịp
        thị giác khác hẳn nhau.

        Nay tách thành một `<section>` riêng, đủ chiều ngang, và chia
        theo ĐÚNG BẢN CHẤT của hai loại dữ liệu:

          - Phân loại là một CHUỖI có thứ bậc  -> cột hẹp, xếp dọc
          - Đặc điểm là những Ô RỜI ngang hàng -> lưới thẻ, giống hệt
            cách khối "Chăm sóc cây" bày các mục
    --}}
    <section class="classify">

        <h2 class="text-h3 classify__title">Phân loại &amp; đặc điểm</h2>

        <div class="classify__grid">

            @if($taxon)
                <div class="classify__taxonomy">
                    <p class="classify__sub">Phân loại khoa học</p>

                    {{--
                        Hiện CẢ CHUỖI từ Giới xuống, không chỉ bậc cuối.
                        "Họ Ráy" đứng một mình không nói lên gì với người
                        chưa biết; cả chuỗi thì đọc là hiểu ngay cây này
                        đứng ở đâu. Mỗi bậc là một liên kết — đó chính là
                        cách khách đi từ một cây họ thích sang cây cùng
                        nhóm.
                    --}}
                    <ol class="taxon-chain">
                        @foreach($taxon->chain() as $nut)
                            <li>
                                <a href="{{ route('shop.taxa.show', $nut) }}" class="taxon-chain__item">
                                    <span class="taxon-chain__rank">{{ $nut->rank->label() }}</span>
                                    <span class="taxon-chain__name">
                                        {{ $nut->name }}
                                        @if($nut->scientific_name)
                                            @if($nut->rank->italic())
                                                {{-- Chi và Loài viết nghiêng — quy ước quốc tế. --}}
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
                        {{--
                            VÌ SAO CHUỖI DỪNG SỚM.

                            Không có dòng này thì chuỗi cụt ở bậc Chi
                            trông y hệt dữ liệu làm dở — trong khi đó là
                            câu trả lời ĐÚNG: hàng thương mại là giống
                            lai, hoặc một chậu có nhiều loài.
                        --}}
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

                    {{--
                        LƯỚI THẺ, không phải danh sách dòng.

                        Cùng nhịp với khối "Chăm sóc cây" bên trên: nhãn
                        nhỏ ở trên, nội dung đậm ở dưới. Hai khối cạnh
                        nhau đọc như một hệ thống thay vì hai mảnh ghép
                        từ hai trang khác nhau.
                    --}}
                    <div class="classify__cards">
                        @foreach($nhomNhan as $nhom)
                            <div class="classify__card">
                                <span class="classify__card-label">{{ $nhom['type']->label() }}</span>

                                <span class="classify__card-values">
                                    @foreach($nhom['values'] as $value)
                                        {{-- Mỗi nhãn là một liên kết tới bộ lọc: đọc thấy
                                             "cây mọng nước" và bấm để xem còn cây nào nữa.
                                             Nhãn không bấm được thì chỉ là chữ. --}}
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
