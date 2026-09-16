<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CommunityReaction;
use App\Enums\CommunityReportReason;
use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\Product;
use App\Models\User;
use App\Services\Community\CommunityException;
use App\Services\Community\CommunityInteraction;
use App\Services\Community\CommunityMediaStore;
use App\Services\Community\CommunityReports;
use App\Services\Points\CommunityReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Góc cây của bạn" — bảng tin khoe cây kiểu mạng xã hội, cố ý gọn.
 * ⚠️ HAI LUẬT KHÔNG ĐƯỢC NỚI
 */
class CommunityController extends Controller
{
    private const MOI_TRANG = 10;

    private const TAB = ['moi-nhat', 'thich-nhieu', 'da-luu', 'cua-toi'];

    public function index(Request $request, CommunityReward $thuong): View|RedirectResponse
    {
        $tab = in_array($request->query('tab'), self::TAB, true) ? $request->query('tab') : 'moi-nhat';

        if ($request->query('sap-xep') === 'thich-nhieu') {
            $tab = 'thich-nhieu';
        }

        if (in_array($tab, ['da-luu', 'cua-toi'], true) && ! Auth::check()) {
            return redirect()->route('login');
        }

        $query = $tab === 'cua-toi'
            ? CommunityPost::query()->where('user_id', Auth::id())->latest()
            : CommunityPost::query()->approved();

        match ($tab) {
            'thich-nhieu' => $query->orderByDesc('likers_count')->latest('approved_at'),
            'da-luu' => $query->whereIn('id', DB::table('community_post_saves')->where('user_id', Auth::id())->select('community_post_id'))->latest('approved_at'),
            'moi-nhat' => $query->latest('approved_at'),
            default => null,
        };

        $posts = $query
            ->with([
                'user:id,name',
                'product:id,name,slug,main_image',
                'media',
                'comments' => fn ($q) => $q->visible()->root()->with('user:id,name')->latest()->limit(2),
            ])
            ->withCount(['likers', 'comments as so_binh_luan' => fn ($q) => $q->visible()])
            ->paginate(self::MOI_TRANG)
            ->withQueryString();

        $ids = $posts->pluck('id')->all();

        $idBinhLuan = $posts->getCollection()
            ->flatMap(fn ($p) => $p->relationLoaded('comments') ? $p->comments->pluck('id') : collect())
            ->all();

        return view('shop.community.index', [
            'posts' => $posts,
            'tab' => $tab,
            'camXucCuaToi' => $this->camXucCuaToi($ids),
            'tomTatCamXuc' => $this->tomTatCamXuc($ids),
            'camXucBL' => $this->camXucBinhLuan($idBinhLuan),
            'daLuu' => $this->cuaToiTrong('community_post_saves', $ids),
            'diemBai' => $tab === 'cua-toi' ? $thuong->daThuong($posts->getCollection()) : [],
            'cayDaMua' => Auth::check() ? $this->cayDaMua() : collect(),
            'coTheBinhLuan' => app(CommunityInteraction::class)->coTheBinhLuan(Auth::user()),
            'soChoDuyetCuaToi' => Auth::check() ? CommunityPost::where('user_id', Auth::id())->pending()->count() : 0,
        ]);
    }

    public function profile(int $user, CommunityReward $thuong): View
    {
        $nguoi = User::select(['id', 'name', 'created_at'])->findOrFail($user);
        $laToi = Auth::id() === $nguoi->id;

        $query = CommunityPost::query()->where('user_id', $nguoi->id);

        if (! $laToi) {
            $query->approved();
        }

        $posts = $query
            ->with([
                'user:id,name',
                'product:id,name,slug,main_image',
                'media',
                'comments' => fn ($q) => $q->visible()->root()->with('user:id,name')->latest()->limit(2),
            ])
            ->withCount(['likers', 'comments as so_binh_luan' => fn ($q) => $q->visible()])
            ->orderByRaw('CASE WHEN pinned_at IS NULL THEN 1 ELSE 0 END')
            ->latest('id')
            ->paginate(self::MOI_TRANG)
            ->withQueryString();

        $ids = $posts->pluck('id')->all();
        $idBinhLuan = $posts->getCollection()
            ->flatMap(fn ($p) => $p->relationLoaded('comments') ? $p->comments->pluck('id') : collect())
            ->all();

        $baiHien = CommunityPost::approved()->where('user_id', $nguoi->id)->select('id');

        return view('shop.community.profile', [
            'nguoi' => $nguoi,
            'laToi' => $laToi,
            'posts' => $posts,
            'thongKe' => [
                'bai' => (clone $baiHien)->count(),
                'cho_duyet' => $laToi ? CommunityPost::where('user_id', $nguoi->id)->pending()->count() : 0,
                'dang_an' => $laToi ? CommunityPost::where('user_id', $nguoi->id)
                    ->where(fn ($q) => $q->whereNotNull('author_hidden_at')->orWhereNotNull('hidden_at'))->count() : 0,
                'cam_xuc' => DB::table('community_post_likes')->whereIn('community_post_id', $baiHien)->count(),
                'binh_luan' => CommunityComment::visible()->whereIn('community_post_id', $baiHien)->count(),
            ],
            'camXucCuaToi' => $this->camXucCuaToi($ids),
            'tomTatCamXuc' => $this->tomTatCamXuc($ids),
            'camXucBL' => $this->camXucBinhLuan($idBinhLuan),
            'daLuu' => $this->cuaToiTrong('community_post_saves', $ids),
            'diemBai' => $laToi ? $thuong->daThuong($posts->getCollection()) : [],
            'coTheBinhLuan' => app(CommunityInteraction::class)->coTheBinhLuan(Auth::user()),
        ]);
    }

    public function show(int $post, CommunityInteraction $tuongTac): View
    {
        $bai = CommunityPost::query()
            ->where(function ($q) {
                $q->approved();

                if (Auth::check()) {
                    $q->orWhere(fn ($w) => $w->authorHidden(Auth::id()));
                }
            })
            ->with(['user:id,name', 'product:id,name,slug,main_image', 'media'])
            ->withCount(['likers', 'comments as so_binh_luan' => fn ($q) => $q->visible()])
            ->findOrFail($post);

        $tuAn = fn ($q) => Auth::id() === (int) $bai->user_id
            ? $q->where(fn ($w) => $w->whereNull('hidden_at')->orWhere('hidden_by', Auth::id()))
            : $q->visible();

        $binhLuan = $bai->comments()
            ->where(fn ($q) => $tuAn($q))
            ->root()
            ->with([
                'user:id,name',
                'replies' => fn ($q) => $tuAn($q)->with(['user:id,name', 'replyTo:id,name']),
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $idBinhLuan = $binhLuan->flatMap(fn ($bl) => [$bl->id, ...$bl->replies->pluck('id')])->all();

        return view('shop.community.show', [
            'post' => $bai,
            'comments' => $binhLuan,
            'camXucBL' => $this->camXucBinhLuan($idBinhLuan),
            'camXucCuaToi' => $this->camXucCuaToi([$bai->id]),
            'tomTatCamXuc' => $this->tomTatCamXuc([$bai->id]),
            'daLuu' => $this->cuaToiTrong('community_post_saves', [$bai->id]) !== [],
            'coTheBinhLuan' => $tuongTac->coTheBinhLuan(Auth::user()),
        ]);
    }

    public function like(Request $request, int $post, CommunityInteraction $tuongTac): JsonResponse|RedirectResponse
    {
        $bai = CommunityPost::approved()->findOrFail($post);

        $data = $request->validate(
            ['cam_xuc' => ['nullable', Rule::enum(CommunityReaction::class)]],
            [],
            ['cam_xuc' => 'cảm xúc'],
        );

        $ket = $tuongTac->doiThich(
            Auth::user(),
            $bai,
            isset($data['cam_xuc']) ? CommunityReaction::from($data['cam_xuc']) : null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'thich' => $ket['co'],
                'loai' => $ket['loai']?->value,
                'so' => $bai->likers()->count(),
                'tom_tat' => $this->tomTatCamXuc([$bai->id])[$bai->id] ?? [],
            ]);
        }

        return $this->veBai($bai);
    }

    public function save(Request $request, int $post, CommunityInteraction $tuongTac): JsonResponse|RedirectResponse
    {
        $bai = CommunityPost::approved()->findOrFail($post);
        $dangLuu = $tuongTac->doiLuu(Auth::user(), $bai);

        if ($request->expectsJson()) {
            return response()->json(['luu' => $dangLuu]);
        }

        return $this->veBai($bai)->with('success', $dangLuu ? 'Đã lưu bài. Xem lại ở mục "Đã lưu".' : 'Đã bỏ lưu bài.');
    }

    public function comment(Request $request, int $post, CommunityInteraction $tuongTac): RedirectResponse
    {
        $bai = CommunityPost::approved()->findOrFail($post);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . CommunityInteraction::DO_DAI_BINH_LUAN],
            'tra_loi' => ['nullable', 'integer'],
        ], [], ['body' => 'bình luận']);

        try {
            $bl = $tuongTac->binhLuan(Auth::user(), $bai, $data['body'], isset($data['tra_loi']) ? (int) $data['tra_loi'] : null);
        } catch (CommunityException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->to(route('shop.community.show', $bai->id) . '#binh-luan-' . $bl->id);
    }

    public function reactComment(Request $request, int $comment, CommunityInteraction $tuongTac): JsonResponse|RedirectResponse
    {
        $bl = CommunityComment::visible()
            ->whereIn('community_post_id', CommunityPost::approved()->select('id'))
            ->findOrFail($comment);

        $data = $request->validate(
            ['cam_xuc' => ['nullable', Rule::enum(CommunityReaction::class)]],
            [],
            ['cam_xuc' => 'cảm xúc'],
        );

        $ket = $tuongTac->doiCamXucBinhLuan(
            Auth::user(),
            $bl,
            isset($data['cam_xuc']) ? CommunityReaction::from($data['cam_xuc']) : null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'thich' => $ket['co'],
                'loai' => $ket['loai']?->value,
                'so' => DB::table('community_comment_reactions')->where('community_comment_id', $bl->id)->count(),
                'tom_tat' => $this->camXucBinhLuan([$bl->id])['tom_tat'][$bl->id] ?? [],
            ]);
        }

        return back();
    }

    public function updateComment(Request $request, int $comment, CommunityInteraction $tuongTac): RedirectResponse
    {
        $bl = CommunityComment::where('user_id', Auth::id())->findOrFail($comment);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . CommunityInteraction::DO_DAI_BINH_LUAN],
        ], [], ['body' => 'bình luận']);

        try {
            $tuongTac->suaBinhLuan(Auth::user(), $bl, $data['body']);
        } catch (CommunityException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to(route('shop.community.show', $bl->community_post_id) . '#binh-luan-' . $bl->id)
            ->with('success', 'Đã sửa bình luận.');
    }

    public function destroyComment(int $comment, CommunityReports $baoCao): RedirectResponse
    {
        $bl = CommunityComment::where('user_id', Auth::id())->findOrFail($comment);

        DB::transaction(function () use ($bl, $baoCao) {
            $baoCao->donCuaBinhLuan($bl);
            $bl->delete();
        });

        return back()->with('success', 'Đã gỡ bình luận của bạn.');
    }

    public function store(Request $request, CommunityMediaStore $kho): RedirectResponse
    {
        $data = $request->validate($this->quyTacBai() + CommunityMediaStore::quyTac(), $this->thongBao(), $this->tenO());

        $tep = $request->file('media', []);

        $post = DB::transaction(function () use ($data, $tep, $kho) {
            $post = new CommunityPost([
                'body' => trim((string) ($data['body'] ?? '')),
                'product_id' => $data['product_id'] ?? null,
            ]);
            $post->user_id = Auth::id();
            $post->save();

            $kho->them($post, $tep);

            return $post;
        });

        return redirect()->route('shop.community.index', ['tab' => 'cua-toi'])->with(
            'success',
            'Đã gửi bài. Cửa hàng sẽ duyệt trước khi đăng — thường trong ngày. Bài đang nằm ở mục "Bài của tôi".',
        );
    }

    public function edit(int $post): View
    {
        $bai = CommunityPost::where('user_id', Auth::id())->with('media')->findOrFail($post);

        return view('shop.community.edit', [
            'post' => $bai,
            'cayDaMua' => $this->cayDaMua(),
        ]);
    }

    public function update(Request $request, int $post, CommunityMediaStore $kho): RedirectResponse
    {
        $bai = CommunityPost::where('user_id', Auth::id())->with('media')->findOrFail($post);

        $xoa = collect((array) $request->input('xoa_media', []))->map(fn ($id) => (int) $id);
        $conLai = $bai->media->reject(fn ($m) => $xoa->contains($m->id));

        $data = $request->validate(
            $this->quyTacBai(coTepCu: $conLai->isNotEmpty()) + CommunityMediaStore::quyTac($conLai->count(), $conLai->filter->laVideo()->count()) + [
                'xoa_media' => ['nullable', 'array'],
                'xoa_media.*' => ['integer', Rule::in($bai->media->pluck('id')->all())],
            ],
            $this->thongBao(),
            $this->tenO(),
        );

        $daDang = $bai->isApproved() || $bai->isHidden() || $bai->isRejected();

        DB::transaction(function () use ($bai, $data, $request, $kho, $xoa) {
            foreach ($bai->media->filter(fn ($m) => $xoa->contains($m->id)) as $m) {
                $kho->xoa($m);
            }

            $kho->them($bai, $request->file('media', []));

            $bai->fill([
                'body' => trim((string) ($data['body'] ?? '')),
                'product_id' => $data['product_id'] ?? null,
            ]);
            $bai->forceFill([
                'edited_at' => now(),
                'approved_at' => null,
                'rejected_at' => null,
                'reject_reason' => null,
                'hidden_at' => null,
                'hidden_reason' => null,
            ])->save();
        });

        return redirect()->route('shop.community.index', ['tab' => 'cua-toi'])->with(
            'success',
            $daDang ? 'Đã lưu thay đổi. Bài được gửi duyệt lại trước khi hiện.' : 'Đã lưu thay đổi.',
        );
    }

    public function destroy(int $post, CommunityMediaStore $kho, CommunityReports $baoCao): RedirectResponse
    {
        $bai = CommunityPost::where('user_id', Auth::id())->findOrFail($post);

        $kho->xoaCuaBai($bai);
        $baoCao->donCuaBai($bai);
        $bai->delete();

        return redirect()->route('shop.community.index', ['tab' => 'cua-toi'])->with('success', 'Đã xoá bài của bạn.');
    }

    public function report(Request $request, CommunityReports $baoCao): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'loai' => ['required', Rule::in([CommunityReport::BAI, CommunityReport::BINH_LUAN])],
            'id' => ['required', 'integer'],
            'ly_do' => ['required', Rule::enum(CommunityReportReason::class)],
            'ghi_chu' => ['nullable', 'string', 'max:300'],
        ], [], ['ly_do' => 'lý do', 'ghi_chu' => 'ghi chú']);

        try {
            $baoCao->baoCao(Auth::user(), $data['loai'], (int) $data['id'], CommunityReportReason::from($data['ly_do']), $data['ghi_chu'] ?? null);
        } catch (CommunityException $e) {
            return $request->expectsJson()
                ? response()->json(['loi' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        $cam = 'Cảm ơn bạn đã báo cáo. Cửa hàng sẽ xem xét và xử lý nội dung vi phạm.';

        return $request->expectsJson() ? response()->json(['thong_bao' => $cam]) : back()->with('success', $cam);
    }

    private function quyTacBai(bool $coTepCu = false): array
    {
        return [
            'body' => [$coTepCu ? 'nullable' : 'required_without:media', 'nullable', 'string', 'max:2000'],

            'product_id' => ['nullable', 'integer', Rule::in($this->cayDaMua()->pluck('id')->all())],
        ];
    }

    private function thongBao(): array
    {
        return [
            'body.required_without' => 'Viết vài dòng hoặc thêm ít nhất một ảnh / video.',
            'media.*.mimetypes' => 'Chỉ nhận ảnh JPG, PNG, WebP hoặc video MP4, WebM.',
            'media.*.uploaded' => 'Tệp quá lớn để tải lên.',
        ];
    }

    private function tenO(): array
    {
        return ['body' => 'nội dung', 'media' => 'ảnh / video', 'media.*' => 'ảnh / video', 'product_id' => 'cây'];
    }

    private function veBai(CommunityPost $bai): RedirectResponse
    {
        return redirect()->to(strtok(url()->previous(), '#') . '#bai-' . $bai->id);
    }

    private function camXucBinhLuan(array $ids): array
    {
        $rong = ['cua_toi' => [], 'so' => [], 'tom_tat' => []];

        if ($ids === []) {
            return $rong;
        }

        $gop = DB::table('community_comment_reactions')
            ->selectRaw('community_comment_id, reaction, COUNT(*) as so')
            ->whereIn('community_comment_id', $ids)
            ->groupBy('community_comment_id', 'reaction')
            ->orderByDesc('so')
            ->get();

        return [
            'cua_toi' => Auth::check()
                ? DB::table('community_comment_reactions')
                    ->where('user_id', Auth::id())
                    ->whereIn('community_comment_id', $ids)
                    ->pluck('reaction', 'community_comment_id')
                    ->map(fn ($r) => (string) $r)
                    ->all()
                : [],

            'so' => $gop->groupBy('community_comment_id')->map(fn ($n) => (int) $n->sum('so'))->all(),

            'tom_tat' => $gop->groupBy('community_comment_id')
                ->map(fn ($n) => $n->map(fn ($d) => ['loai' => (string) $d->reaction, 'so' => (int) $d->so])->values()->all())
                ->all(),
        ];
    }

    private function camXucCuaToi(array $ids): array
    {
        if (! Auth::check() || $ids === []) {
            return [];
        }

        return DB::table('community_post_likes')
            ->where('user_id', Auth::id())
            ->whereIn('community_post_id', $ids)
            ->pluck('reaction', 'community_post_id')
            ->map(fn ($r) => (string) $r)
            ->all();
    }

    private function tomTatCamXuc(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('community_post_likes')
            ->selectRaw('community_post_id, reaction, COUNT(*) as so')
            ->whereIn('community_post_id', $ids)
            ->groupBy('community_post_id', 'reaction')
            ->orderByDesc('so')
            ->get()
            ->groupBy('community_post_id')
            ->map(fn ($nhom) => $nhom->map(fn ($d) => ['loai' => (string) $d->reaction, 'so' => (int) $d->so])->values()->all())
            ->all();
    }

    private function cuaToiTrong(string $bang, array $ids): array
    {
        if (! Auth::check() || $ids === []) {
            return [];
        }

        return DB::table($bang)
            ->where('user_id', Auth::id())
            ->whereIn('community_post_id', $ids)
            ->pluck('community_post_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function cayDaMua(): Collection
    {
        return Product::query()
            ->whereHas('orderItems.order', fn ($q) => $q->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'main_image']);
    }
}
