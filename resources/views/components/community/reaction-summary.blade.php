@props(['tomTat' => [], 'count' => 0])

{{-- Mấy biểu tượng cảm xúc nhiều nhất + tổng số, như dòng tóm tắt dưới bài của mạng xã hội. --}}
<span class="cam-xuc-tomtat" data-tom-tat-thich>
    @foreach(collect($tomTat)->take(3) as $ct)
        @php($cx = \App\Enums\CommunityReaction::tryFrom($ct['loai']))
        @if($cx)
            <span class="cam-xuc-tomtat__icon {{ $cx->mau() }}" title="{{ $cx->label() }}: {{ $ct['so'] }}">
                <x-site.icon :name="$cx->icon()" />
            </span>
        @endif
    @endforeach
    <span data-so-cam-xuc>{{ $count }}</span> cảm xúc
</span>
