@extends('layouts.admin')

@section('title', 'Góc cây của bạn')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Góc cây của bạn</h1>
    <p class="admin-page-subtitle mb-0">
        Bài do khách đăng. <strong>Duyệt trước khi hiện</strong> — chưa duyệt thì không ai
        ngoài chính người đăng nhìn thấy.
    </p>
</div>

{{-- Tab mang luôn con số: admin biết còn việc mà không phải bấm vào xem. --}}
<div class="filter-chip-group mb-4">
    @foreach([
        'cho-duyet' => 'Chờ duyệt' . ($soChoDuyet > 0 ? ' (' . $soChoDuyet . ')' : ''),
        'da-duyet' => 'Đã duyệt',
        'tu-choi' => 'Đã từ chối',
    ] as $key => $nhan)
        <a href="{{ route('admin.community.index', ['loc' => $key]) }}"
           class="filter-chip {{ $loc === $key ? 'is-active' : '' }}">{{ $nhan }}</a>
    @endforeach
</div>

@if($posts->isEmpty())
    <div class="admin-panel p-4">
        <x-site.empty-state
            title="Không có bài nào ở mục này"
            text="{{ $loc === 'cho-duyet' ? 'Đã duyệt hết — không còn gì chờ.' : 'Chưa có bài nào.' }}" />
    </div>
@else
    <div class="d-flex flex-column gap-3">
        @foreach($posts as $post)
            <div class="admin-panel p-4">
                <div class="row g-3">
                    @if($post->photo)
                        <div class="col-md-3">
                            <x-site.image :path="$post->photo" alt="Ảnh khách gửi" class="admin-thumb-lg" />
                        </div>
                    @endif

                    <div class="col">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div>
                                <strong>{{ $post->user?->name ?? 'Người dùng đã xoá' }}</strong>
                                <span class="admin-page-subtitle">
                                    {{ $post->user?->email }} · {{ $post->created_at->format('d/m/Y H:i') }}
                                </span>
                            </div>

                            <span class="status-pill status-pill--{{ $post->statusBadge() }}">
                                {{ $post->statusText() }}
                            </span>
                        </div>

                        <p class="mb-2">{{ $post->body }}</p>

                        @if($post->product)
                            <p class="admin-page-subtitle mb-2">
                                Gắn với: <a href="{{ route('admin.products.edit', $post->product) }}">{{ $post->product->name }}</a>
                            </p>
                        @endif

                        @if($post->isRejected() && $post->reject_reason)
                            <p class="admin-page-subtitle mb-2">Lý do từ chối: {{ $post->reject_reason }}</p>
                        @endif

                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            @if(! $post->isApproved())
                                <form method="POST" action="{{ route('admin.community.approve', $post) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-primary-brand btn-sm">Duyệt</button>
                                </form>
                            @endif

                            @if(! $post->isRejected())
                                {{-- LÝ DO BẮT BUỘC: từ chối im lặng thì khách đăng lại y hệt. --}}
                                <form method="POST" action="{{ route('admin.community.reject', $post) }}"
                                      class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="reject_reason" class="form-control form-control-sm"
                                           maxlength="200" required style="min-width: 16rem;"
                                           placeholder="Lý do từ chối (khách sẽ đọc được)"
                                           aria-label="Lý do từ chối">
                                    <button type="submit" class="btn btn-ghost btn-sm">Từ chối</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.community.destroy', $post) }}"
                                  onsubmit="return confirm('Xoá hẳn bài này cùng ảnh? Không khôi phục được.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm text-danger">Xoá hẳn</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-3">{{ $posts->links() }}</div>
@endif

@endsection
