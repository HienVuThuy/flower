@extends('layouts.admin')

@section('title', 'Góc cây của bạn')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Góc cây của bạn</h1>
    <p class="admin-page-subtitle mb-0">
        Bài do khách đăng. <strong>Duyệt trước khi hiện</strong> — chưa duyệt thì không ai ngoài chính người đăng nhìn thấy.
        Bình luận hiện ngay nên xử lý sau: ẩn một chạm, và khách báo cáo được nội dung xấu.
    </p>
</div>

{{-- Tab mang luôn con số: admin biết còn việc mà không phải bấm vào xem. --}}
<div class="filter-chip-group mb-4">
    @foreach([
        'bao-cao' => 'Báo cáo' . ($soBaoCao > 0 ? ' (' . $soBaoCao . ')' : ''),
        'cho-duyet' => 'Chờ duyệt' . ($soChoDuyet > 0 ? ' (' . $soChoDuyet . ')' : ''),
        'da-duyet' => 'Đã duyệt',
        'da-an' => 'Đang bị ẩn',
        'tu-choi' => 'Đã từ chối',
        'binh-luan' => 'Bình luận',
    ] as $key => $nhan)
        <a href="{{ route('admin.community.index', ['loc' => $key]) }}"
           class="filter-chip {{ $loc === $key ? 'is-active' : '' }}">{{ $nhan }}</a>
    @endforeach
</div>

@if($loc === 'bao-cao')

    @if($hangBaoCao->isEmpty())
        <div class="admin-panel p-4">
            <x-site.empty-state title="Không có báo cáo nào" text="Khách chưa báo nội dung nào, hoặc đã xử lý hết." />
        </div>
    @else
        <div class="d-flex flex-column gap-3">
            @foreach($hangBaoCao as $muc)
                @php($nd = $muc['noi_dung'])
                <div class="admin-panel p-4" data-bao-cao="{{ $muc['loai'] }}-{{ $muc['id'] }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <strong>{{ $muc['loai'] === 'post' ? 'Bài viết' : 'Bình luận' }} · {{ $muc['so'] }} lượt báo cáo</strong>
                        <span class="admin-page-subtitle">Mới nhất: <x-site.time :at="$muc['moi_nhat']" format="d/m/Y H:i" /></span>
                    </div>

                    <p class="admin-page-subtitle mb-2">
                        Lý do:
                        @foreach($muc['ly_do'] as $ten => $so)
                            <span class="badge text-bg-secondary">{{ $ten }} ×{{ $so }}</span>
                        @endforeach
                    </p>

                    @foreach($muc['ghi_chu'] as $ghi)
                        <p class="admin-page-subtitle mb-1">“{{ $ghi }}”</p>
                    @endforeach

                    @if(! $nd)
                        <p class="mb-3">Nội dung đã bị xoá.</p>
                    @else
                        <div class="admin-panel p-3 mb-3" style="background: var(--surface-alt);">
                            <strong>{{ $nd->user?->name ?? 'Người dùng đã xoá' }}</strong>
                            <span class="admin-page-subtitle">{{ $nd->user?->email }}</span>
                            <p class="mb-2 mt-1">{{ $nd->body }}</p>

                            @if($muc['loai'] === 'post')
                                @if($nd->media->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        @foreach($nd->media as $m)
                                            @if($m->laVideo())
                                                <video class="admin-thumb" controls preload="metadata" src="{{ $m->url() }}#t=0.1"></video>
                                            @else
                                                <x-site.image :path="$m->path" alt="Ảnh khách gửi" class="admin-thumb" />
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                <a href="{{ route('shop.community.show', $nd->id) }}" target="_blank" rel="noopener">Xem bài trên trang</a>
                                @if($nd->hidden_at)
                                    <span class="badge text-bg-secondary">đang bị ẩn</span>
                                @endif
                            @else
                                @if($nd->post)
                                    <a href="{{ route('shop.community.show', $nd->post->id) }}" target="_blank" rel="noopener">
                                        Dưới bài: {{ \Illuminate\Support\Str::limit($nd->post->body, 60) }}
                                    </a>
                                @endif
                                @if($nd->hidden_at)
                                    <span class="badge text-bg-secondary">đang bị ẩn</span>
                                @endif
                            @endif
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <form method="POST" action="{{ route('admin.community.reports.handle') }}" class="d-flex flex-wrap gap-2">
                            @csrf
                            <input type="hidden" name="loai" value="{{ $muc['loai'] }}">
                            <input type="hidden" name="id" value="{{ $muc['id'] }}">
                            <input type="hidden" name="ket_qua" value="an">
                            @if($muc['loai'] === 'post')
                                <input type="text" name="ly_do" class="form-control form-control-sm" style="min-width: 16rem;"
                                       maxlength="200" required placeholder="Lý do ẩn (tác giả đọc được)" aria-label="Lý do ẩn">
                            @endif
                            <button type="submit" class="btn btn-outline-admin btn-sm">Ẩn nội dung</button>
                        </form>

                        <form method="POST" action="{{ route('admin.community.reports.handle') }}">
                            @csrf
                            <input type="hidden" name="loai" value="{{ $muc['loai'] }}">
                            <input type="hidden" name="id" value="{{ $muc['id'] }}">
                            <input type="hidden" name="ket_qua" value="bo-qua">
                            <button type="submit" class="btn btn-ghost btn-sm">Không vi phạm</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@elseif($loc === 'binh-luan')

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
                                @if($bl->parent)
                                    <span class="d-block admin-page-subtitle small">
                                        Trả lời: {{ \Illuminate\Support\Str::limit($bl->parent->body, 50) }}
                                    </span>
                                @endif
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
                    @if($post->media->isNotEmpty())
                        <div class="col-md-4">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($post->media as $m)
                                    @if($m->laVideo())
                                        <video class="admin-thumb" controls preload="metadata" src="{{ $m->url() }}#t=0.1"></video>
                                    @else
                                        <x-site.image :path="$m->path" alt="Ảnh khách gửi" class="admin-thumb" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="col">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div>
                                <strong>{{ $post->user?->name ?? 'Người dùng đã xoá' }}</strong>
                                <span class="admin-page-subtitle">
                                    {{ $post->user?->email }} · <x-site.time :at="$post->created_at" format="d/m/Y H:i" />
                                    @if($post->edited_at) · đã sửa lúc <x-site.time :at="$post->edited_at" format="d/m/Y H:i" /> @endif
                                </span>
                            </div>

                            <span class="status-pill status-pill--{{ $post->statusBadge() }}">{{ $post->statusText() }}</span>
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

                        @if($post->isHidden() && $post->hidden_reason)
                            <p class="admin-page-subtitle mb-2">Lý do ẩn: {{ $post->hidden_reason }}</p>
                        @endif

                        @isset($diemBai[$post->id])
                            <p class="admin-page-subtitle mb-2" data-diem-bai="{{ $post->id }}">Đã thưởng {{ $diemBai[$post->id] }} điểm</p>
                        @endisset

                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            @if(! $post->isApproved())
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

                            @if($post->isApproved())
                                <form method="POST" action="{{ route('admin.community.hide', $post) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    @unless($post->isHidden())
                                        <input type="text" name="hidden_reason" class="form-control form-control-sm" style="min-width: 14rem;"
                                               maxlength="200" required placeholder="Lý do ẩn (tác giả đọc được)" aria-label="Lý do ẩn">
                                    @endunless
                                    <button type="submit" class="btn btn-outline-admin btn-sm">{{ $post->isHidden() ? 'Hiện lại bài' : 'Ẩn bài' }}</button>
                                </form>
                            @endif

                            @if(! $post->isRejected())
                                <form method="POST" action="{{ route('admin.community.reject', $post) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="reject_reason" class="form-control form-control-sm"
                                           maxlength="200" required style="min-width: 16rem;"
                                           placeholder="Lý do từ chối (khách sẽ đọc được)" aria-label="Lý do từ chối">
                                    <button type="submit" class="btn btn-ghost btn-sm">Từ chối</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.community.destroy', $post) }}"
                                  onsubmit="return confirm('Xoá hẳn bài này cùng ảnh và video? Không khôi phục được.')">
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
