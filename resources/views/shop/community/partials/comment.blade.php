{{-- MỘT BÌNH LUẬN và các câu trả lời của nó. --}}
@php
    $laCuaToi = auth()->id() === $bl->user_id;
    $laChuBai = auth()->check() && auth()->id() === (int) $post->user_id;
    $blDaAn = $bl->hidden_at !== null;
    $choPhepTraLoi = ($coTheBinhLuan ?? false) && ! $post->khoaBinhLuan() && ! $blDaAn;
    $cacTraLoi = $traLoi ?? [];
    $doDai = \App\Services\Community\CommunityInteraction::DO_DAI_BINH_LUAN;
@endphp

<div class="comment {{ $blDaAn ? 'comment--an' : '' }}" id="binh-luan-{{ $bl->id }}" data-binh-luan="{{ $bl->id }}">
    <span class="avatar avatar--sm" aria-hidden="true">{{ mb_substr($bl->user?->name ?? 'K', 0, 1) }}</span>

    <div class="comment__body">
        <div class="comment__bubble">
            @if($bl->user)
                <a href="{{ route('shop.community.profile', $bl->user->id) }}" class="comment__author">{{ $bl->user->name }}</a>
            @else
                <span class="comment__author">Người dùng đã xoá</span>
            @endif
            @if($bl->replyTo)
                <span class="comment__reply-to">trả lời {{ $bl->replyTo->name }}</span>
            @endif
            <p class="comment__text">{{ $bl->body }}</p>
        </div>

        @if($blDaAn)
            <p class="comment__an-note" data-bl-an>Bạn đang ẩn bình luận này — chỉ mình bạn thấy.</p>
        @endif

        <div class="comment__tools">
            <x-site.time :at="$bl->created_at" relative />
            @if($bl->edited_at)
                <span class="comment__edited">· đã sửa</span>
            @endif

            <x-community.comment-reaction
                :comment="$bl"
                :cam-xuc="($camXucBL['cua_toi'] ?? [])[$bl->id] ?? null"
                :count="($camXucBL['so'] ?? [])[$bl->id] ?? 0"
                :tom-tat="($camXucBL['tom_tat'] ?? [])[$bl->id] ?? []" />

            @auth
                @if($choPhepTraLoi)
                    <details class="comment-form-toggle">
                        <summary class="comment__tool">Trả lời</summary>
                        <form method="POST" action="{{ route('shop.community.comment', $post->id) }}" class="comment-form">
                            @csrf
                            <input type="hidden" name="tra_loi" value="{{ $bl->id }}">
                            <label class="visually-hidden" for="tra-loi-{{ $bl->id }}">Trả lời {{ $bl->user?->name }}</label>
                            <textarea id="tra-loi-{{ $bl->id }}" name="body" rows="2" required maxlength="{{ $doDai }}"
                                      class="form-control" placeholder="Trả lời {{ $bl->user?->name }}…"></textarea>
                            <div class="comment-form__actions">
                                <x-community.emoji-picker :target="'#tra-loi-' . $bl->id" />
                                <button type="submit" class="btn btn-primary-brand btn-sm">Gửi</button>
                            </div>
                        </form>
                    </details>
                @endif

                @if($laCuaToi)
                    <details class="comment-form-toggle">
                        <summary class="comment__tool">Sửa</summary>
                        <form method="POST" action="{{ route('shop.community.comment.update', $bl->id) }}" class="comment-form">
                            @csrf
                            @method('PATCH')
                            <label class="visually-hidden" for="sua-bl-{{ $bl->id }}">Sửa bình luận</label>
                            <textarea id="sua-bl-{{ $bl->id }}" name="body" rows="2" required maxlength="{{ $doDai }}"
                                      class="form-control">{{ $bl->body }}</textarea>
                            <div class="comment-form__actions">
                                <x-community.emoji-picker :target="'#sua-bl-' . $bl->id" />
                                <button type="submit" class="btn btn-primary-brand btn-sm">Lưu</button>
                            </div>
                        </form>
                    </details>

                    <form method="POST" action="{{ route('shop.community.comment.destroy', $bl->id) }}"
                          onsubmit="return confirm('Gỡ bình luận này? Các câu trả lời bên dưới cũng bị gỡ theo.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="comment__tool comment__tool--nut">Gỡ</button>
                    </form>
                @else
                    @if(! $blDaAn)
                        <button type="button" class="comment__tool comment__tool--nut" data-bao-cao
                                data-loai="comment" data-id="{{ $bl->id }}"
                                data-bs-toggle="modal" data-bs-target="#hop-bao-cao">Báo cáo</button>
                    @endif

                    @if($laChuBai)
                        <form method="POST" action="{{ route('shop.community.owner.comment-hide', $bl->id) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="comment__tool comment__tool--nut">
                                {{ $blDaAn ? 'Hiện lại' : 'Ẩn khỏi bài' }}
                            </button>
                        </form>
                    @endif
                @endif
            @endauth
        </div>

        @if(count($cacTraLoi) > 0)
            <div class="comment-replies">
                @foreach($cacTraLoi as $tl)
                    @include('shop.community.partials.comment', [
                        'bl' => $tl,
                        'traLoi' => [],
                        'post' => $post,
                        'coTheBinhLuan' => $coTheBinhLuan ?? false,
                        'camXucBL' => $camXucBL ?? [],
                    ])
                @endforeach
            </div>
        @endif
    </div>
</div>
