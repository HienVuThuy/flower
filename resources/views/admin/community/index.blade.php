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
        'binh-luan' => 'Bình luận',
    ] as $key => $nhan)
        <a href="{{ route('admin.community.index', ['loc' => $key]) }}"
           class="filter-chip {{ $loc === $key ? 'is-active' : '' }}">{{ $nhan }}</a>
    @endforeach
</div>

@if($loc === 'binh-luan')
    {{-- Bình luận hiện ngay nên xử lý sau: mới nhất trước, ẩn / bỏ ẩn một chạm. --}}
    <div class="admin-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Người viết</th>
                        <th scope="col">Bình luận</th>
                        <th scope="col">Dưới bài</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($binhLuan as $bl)
                        <tr data-binh-luan-admin="{{ $bl->id }}">
                            <td>
                                {{ $bl->user?->name ?? 'Người dùng đã xoá' }}
                                <span class="d-block admin-page-subtitle small"><x-site.time :at="$bl->created_at" format="d/m/Y H:i" /></span>
                            </td>
                            <td>
                                {{ $bl->body }}
                                @if($bl->hidden_at)
                                    <span class="badge text-bg-secondary">đã ẩn</span>
                                @endif
                            </td>
                            <td class="admin-page-subtitle small">
                                @if($bl->post)
                                    <a href="{{ route('shop.community.show', $bl->post->id) }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($bl->post->body, 50) }}</a>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.community.comments.toggle', $bl) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-admin">{{ $bl->hidden_at ? 'Hiện lại' : 'Ẩn' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5 text-muted">Chưa có bình luận nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $binhLuan->links() }}</div>
@elseif($posts->isEmpty())
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
                                    {{ $post->user?->email }} · <x-site.time :at="$post->created_at" format="d/m/Y H:i" />
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

                        @isset($diemBai[$post->id])
                            <p class="admin-page-subtitle mb-2" data-diem-bai="{{ $post->id }}">
                                Đã thưởng {{ $diemBai[$post->id] }} điểm
                            </p>
                        @endisset

                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            @if(! $post->isApproved())
                                {{-- Hai nút một biểu mẫu: người duyệt vừa đọc bài, nên cũng là người chấm "nổi bật". --}}
                                <form method="POST" action="{{ route('admin.community.approve', $post) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-primary-brand btn-sm">Duyệt</button>
                                    <button type="submit" name="noi_bat" value="1" class="btn btn-outline-admin btn-sm"
                                            title="Cộng thêm {{ \App\Services\Points\CommunityReward::NOI_BAT }} điểm cho bài có nội dung đáng xem">
                                        Duyệt · bài nổi bật
                                    </button>
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
