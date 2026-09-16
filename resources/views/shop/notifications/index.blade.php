@extends('layouts.app')

@section('title', 'Thông báo')

@section('content')

<section class="section-sm">
    <div class="container-shop community-post">

        <x-site.breadcrumb :items="[['label' => 'Thông báo']]" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h1 class="text-h2 mb-0">Thông báo</h1>

            @if($soChuaDoc > 0)
                <form method="POST" action="{{ route('shop.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Đánh dấu tất cả đã đọc</button>
                </form>
            @endif
        </div>

        @forelse($thongBao as $tb)
            {{--
                CẢ DÒNG LÀ MỘT LIÊN KẾT: bấm vào là đánh dấu đã đọc rồi đi tới
                đúng bài / bình luận. Thông báo mà bấm vào không dẫn đi đâu thì
                khách phải tự đi tìm, và cái chấm "chưa đọc" nằm lại mãi.
            --}}
            <a href="{{ route('shop.notifications.open', $tb->id) }}"
               class="thong-bao {{ $tb->daDoc() ? '' : 'thong-bao--moi' }}"
               data-thong-bao="{{ $tb->id }}">

                <span class="thong-bao__icon" aria-hidden="true"><x-site.icon :name="$tb->type->icon()" /></span>

                <span class="thong-bao__body">
                    <span class="thong-bao__text">
                        @if($tb->type->cuaCuaHang())
                            {{ $tb->type->label() }}.
                        @else
                            <strong>{{ $tb->actor?->name ?? 'Một khách' }}</strong> {{ $tb->type->label() }}.
                        @endif

                        @if($tb->comment)
                            <span class="thong-bao__trich">“{{ \Illuminate\Support\Str::limit($tb->comment->body, 90) }}”</span>
                        @elseif($tb->post)
                            <span class="thong-bao__trich">“{{ \Illuminate\Support\Str::limit($tb->post->body, 90) }}”</span>
                        @endif

                        @if($tb->note)
                            <span class="thong-bao__trich">Lý do: {{ $tb->note }}</span>
                        @endif
                    </span>

                    <span class="thong-bao__gio"><x-site.time :at="$tb->created_at" relative /></span>
                </span>

                @unless($tb->daDoc())
                    <span class="thong-bao__cham" aria-label="Chưa đọc"></span>
                @endunless
            </a>
        @empty
            <x-site.empty-state
                title="Chưa có thông báo nào"
                text="Khi có người bình luận bài của bạn, hoặc cửa hàng duyệt bài, thông báo sẽ hiện ở đây." />
        @endforelse

        <div class="mt-4">{{ $thongBao->links() }}</div>

    </div>
</section>

@endsection
