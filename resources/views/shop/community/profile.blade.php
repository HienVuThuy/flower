@extends('layouts.app')

@section('title', 'Góc cây của ' . $nguoi->name)

@section('meta_description', 'Những bài khoe cây của ' . $nguoi->name . ' trên Góc cây Angevil.')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Góc cây của bạn', 'url' => route('shop.community.index')], ['label' => $nguoi->name]]" />

        {{--
            TRANG CÁ NHÂN — chỗ xem lại toàn bộ bài của một người.

            Người khác xem chỉ thấy bài ĐÃ DUYỆT. Chính chủ xem thì thấy cả bài
            chờ duyệt, bị từ chối và bị ẩn, kèm trạng thái — cùng dữ liệu với
            mục "Bài của tôi", chỉ khác chỗ đứng.
        --}}
        <div class="surface-card p-4 trang-ca-nhan">
            <div class="trang-ca-nhan__dau">
                <span class="avatar avatar--lon" aria-hidden="true">{{ mb_substr($nguoi->name, 0, 1) }}</span>

                <div>
                    <h1 class="text-h2 mb-1">{{ $nguoi->name }}</h1>
                    <p class="text-caption mb-0">
                        Tham gia <x-site.time :at="$nguoi->created_at" format="m/Y" />
                        @if($laToi) · đây là trang của bạn @endif
                    </p>
                </div>
            </div>

            <dl class="trang-ca-nhan__so">
                <div>
                    <dt>Bài đang hiện</dt>
                    <dd data-so-bai>{{ $thongKe['bai'] }}</dd>
                </div>
                <div>
                    <dt>Cảm xúc nhận được</dt>
                    <dd data-so-cam-xuc-nhan>{{ $thongKe['cam_xuc'] }}</dd>
                </div>
                <div>
                    <dt>Bình luận nhận được</dt>
                    <dd data-so-binh-luan-nhan>{{ $thongKe['binh_luan'] }}</dd>
                </div>
            </dl>

            @if($laToi)
                <p class="text-caption mb-0">
                    Bài chờ duyệt, bị từ chối hoặc bị ẩn chỉ mình bạn thấy ở đây.
                    <a href="{{ route('shop.community.index') }}">Về bảng tin để đăng bài mới</a>.
                </p>
            @endif
        </div>

        <div class="trang-ca-nhan__bai mt-4">
            @forelse($posts as $post)
                @include('shop.community.partials.post-card', ['post' => $post])
            @empty
                <x-site.empty-state
                    :title="$laToi ? 'Bạn chưa đăng bài nào' : $nguoi->name . ' chưa có bài nào'"
                    :text="$laToi ? 'Về bảng tin và bấm ô soạn bài để khoe cây của bạn.' : 'Khi có bài được duyệt, bài sẽ hiện ở đây.'" />
            @endforelse

            <div class="mt-4">{{ $posts->links() }}</div>
        </div>

    </div>
</section>

@include('shop.community.partials.report-modal')

@endsection
