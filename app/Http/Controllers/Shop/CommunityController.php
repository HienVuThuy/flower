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
 * ============================================================
 * ⚠️ HAI LUẬT KHÔNG ĐƯỢC NỚI:
 *
 *   1. CHỈ BÀI ĐÃ DUYỆT, KHÔNG BỊ ẨN MỚI HIỆN RA NGOÀI. Đây là nội dung người
 *      lạ đăng lên trang bán hàng. Thích, lưu, bình luận, báo cáo, trang chi
 *      tiết — mọi đường đều lấy bài qua `approved()`.
 *
 *   2. ẢNH / VIDEO ĐI QUA CommunityMediaStore — ảnh bị tước metadata, video
 *      bị xoá toạ độ GPS. Gọi thẳng `$file->store()` là bỏ qua lớp bảo vệ đó.
 *
 * Thích và lưu trả JSON khi được gọi bằng fetch (không tải lại trang, không
 * nhảy lên đầu bảng tin); không có JavaScript thì vẫn là biểu mẫu thường.
 */
class CommunityController extends Controller
{
    private const MOI_TRANG = 10;

    private const TAB = ['moi-nhat', 'thich-nhieu', 'da-luu', 'cua-toi'];

    public function index(Request $request, CommunityReward $thuong): View|RedirectResponse
    {
        $tab = in_array($request->query('tab'), self::TAB, true) ? $request->query('tab') : 'moi-nhat';

        // Tương thích đường dẫn cũ ?sap-xep=thich-nhieu.
        if ($request->query('sap-xep') === 'thich-nhieu') {
            $tab = 'thich-nhieu';
        }

        if (in_array($tab, ['da-luu', 'cua-toi'], true) && ! Auth::check()) {
            return redirect()->route('login');
        }

        $query = $tab === 'cua-toi'
            // Bài của chính mình — KỂ CẢ chưa duyệt, bị từ chối, bị ẩn — kèm trạng thái.
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
                // Xem trước 2 bình luận gốc mới nhất ngay dưới bài, như Facebook.
                'comments' => fn ($q) => $q->visible()->root()->with('user:id,name')->latest()->limit(2),
            ])
            ->withCount(['likers', 'comments as so_binh_luan' => fn ($q) => $q->visible()])
            ->paginate(self::MOI_TRANG)
            ->withQueryString();

        $ids = $posts->pluck('id')->all();

        // Bình luận xem trước dưới mỗi bài cũng có cảm xúc riêng.
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

    /**
     * Trang cá nhân ở Góc cây: bài của một người, kèm vài con số.
     *
     * Người khác xem thì CHỈ thấy bài đã duyệt; chính chủ xem thì thấy cả bài
     * chờ duyệt, bị từ chối và bị ẩn — đúng như mục "Bài của tôi".
     */
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
            ->latest('id')
            ->paginate(self::MOI_TRANG)
            ->withQueryString();

        $ids = $posts->pluck('id')->all();
        $idBinhLuan = $posts->getCollection()
            ->flatMap(fn ($p) => $p->relationLoaded('comments') ? $p->comments->pluck('id') : collect())
            ->all();

        // Con số đếm từ BÀI ĐANG HIỆN của người đó — không tính bài chờ duyệt hay bị ẩn.
        $baiHien = CommunityPost::approved()->where('user_id', $nguoi->id)->select('id');

        return view('shop.community.profile', [
            'nguoi' => $nguoi,
            'laToi' => $laToi,
            'posts' => $posts,
            'thongKe' => [
                'bai' => (clone $baiHien)->count(),
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
            ->approved()
            ->with(['user:id,name', 'product:id,name,slug,main_image', 'media'])
            ->withCount(['likers', 'comments as so_binh_luan' => fn ($q) => $q->visible()])
            ->findOrFail($post);

        $binhLuan = $bai->comments()
            ->visible()
            ->root()
            ->with([
                'user:id,name',
                'replies' => fn ($q) => $q->visible()->with(['user:id,name', 'replyTo:id,name']),
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
                // `thich` giữ tên cũ: đang có cảm xúc hay không.
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

    /** Cảm xúc cho một bình luận đang hiện (bình luận bị ẩn hoặc bài chưa duyệt: 404). */
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
        // Lọc chủ sở hữu trong truy vấn — 404 chứ không 403, cùng cách xoá.
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
            $bl->delete(); // Câu trả lời bên dưới đi theo (cascade).
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

    /**
     * Sửa bài của chính mình.
     *
     * BÀI ĐÃ ĐĂNG QUAY LẠI CHỜ DUYỆT: duyệt trước khi hiện là luật của Góc cây,
     * và "sửa sau khi được duyệt" không được là cửa sau để đăng thứ chưa ai đọc.
     * Bài bị từ chối hay bị ẩn sửa xong cũng gửi duyệt lại — đó là cách khách
     * sửa lỗi. Điểm thưởng đã cộng không cộng lại (khoá theo bài).
     */
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
        /*
         * Lọc theo `user_id` NGAY TRONG TRUY VẤN rồi mới findOrFail — 404 chứ
         * không 403: 403 xác nhận bài đó có tồn tại.
         */
        $bai = CommunityPost::where('user_id', Auth::id())->findOrFail($post);

        // Tệp trên đĩa và báo cáo không có khoá ngoại với tới — dọn tay.
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

    /* ================= BÊN TRONG ================= */

    /** @return array<string, list<mixed>> */
    private function quyTacBai(bool $coTepCu = false): array
    {
        return [
            // Bài chỉ có ảnh / video vẫn đăng được, như mạng xã hội; không có gì thì phải có chữ.
            'body' => [$coTepCu ? 'nullable' : 'required_without:media', 'nullable', 'string', 'max:2000'],

            /*
             * CHỈ GẮN ĐƯỢC CÂY ĐÃ MUA — cùng luật với nhật ký (QĐ-129).
             *
             * Cho gắn sản phẩm bất kỳ thì mục này thành chỗ dựng bằng chứng giả
             * về việc đã mua hàng, ngay cạnh chính sản phẩm đó.
             */
            'product_id' => ['nullable', 'integer', Rule::in($this->cayDaMua()->pluck('id')->all())],
        ];
    }

    /** @return array<string, string> */
    private function thongBao(): array
    {
        return [
            'body.required_without' => 'Viết vài dòng hoặc thêm ít nhất một ảnh / video.',
            'media.*.mimetypes' => 'Chỉ nhận ảnh JPG, PNG, WebP hoặc video MP4, WebM.',
            'media.*.uploaded' => 'Tệp quá lớn để tải lên.',
        ];
    }

    /** @return array<string, string> */
    private function tenO(): array
    {
        return ['body' => 'nội dung', 'media' => 'ảnh / video', 'media.*' => 'ảnh / video', 'product_id' => 'cây'];
    }

    /** Về đúng chỗ bài trên trang vừa đứng — không nhảy lên đầu bảng tin. */
    private function veBai(CommunityPost $bai): RedirectResponse
    {
        return redirect()->to(strtok(url()->previous(), '#') . '#bai-' . $bai->id);
    }

    /**
     * Cảm xúc dưới các BÌNH LUẬN: của tôi, tổng số, và gộp theo loại.
     *
     * Ba truy vấn cho cả trang, không phải mỗi bình luận một lần.
     *
     * @param  list<int>  $ids
     * @return array{cua_toi: array<int, string>, so: array<int, int>, tom_tat: array<int, list<array{loai: string, so: int}>>}
     */
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

    /**
     * Cảm xúc CỦA NGƯỜI ĐANG XEM cho từng bài trong danh sách.
     *
     * @param  list<int>  $ids
     * @return array<int, string> id bài => loại cảm xúc
     */
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

    /**
     * Cảm xúc của MỌI NGƯỜI, gộp theo loại — để hiện mấy biểu tượng dưới bài.
     *
     * Một truy vấn cho cả trang, không phải mỗi bài một lần.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{loai: string, so: int}>>
     */
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

    /**
     * Id những bài trong danh sách mà người đang xem đã lưu.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
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

    /**
     * Những cây người này ĐÃ MUA — lấy từ đơn hàng thật, cùng định nghĩa với nhật ký.
     *
     * @return Collection<int, Product>
     */
    private function cayDaMua(): Collection
    {
        return Product::query()
            ->whereHas('orderItems.order', fn ($q) => $q->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'main_image']);
    }
}
