<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Services\Community\CommunityMediaStore;
use App\Services\Community\CommunityReports;
use App\Services\Points\CommunityReward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Duyệt bài "Góc cây của bạn" và xử lý báo cáo.
 * ============================================================
 * DUYỆT TRƯỚC KHI HIỆN, KHÔNG DUYỆT SAU — bài người lạ đăng lên trang bán hàng.
 *
 * DUYỆT LÀ LÚC THƯỞNG ĐIỂM — xem CommunityReward. Người duyệt vừa đọc bài,
 * nên cũng là người chấm "bài nổi bật".
 *
 * BÌNH LUẬN hiện ngay nên cửa hàng xử lý SAU: tab "Bình luận" (mới nhất trước,
 * ẩn / bỏ ẩn một chạm) và tab "Báo cáo" (khách báo bài / bình luận vi phạm).
 */
class CommunityModerationController extends Controller
{
    public function index(Request $request, CommunityReward $thuong, CommunityReports $baoCao): View
    {
        // Mặc định mở ở việc cần làm: có báo cáo thì báo cáo trước, không thì bài chờ duyệt.
        $soBaoCao = $baoCao->soNoiDungCho();
        $loc = $request->query('loc', $soBaoCao > 0 ? 'bao-cao' : 'cho-duyet');

        $chung = [
            'loc' => $loc,
            'soChoDuyet' => CommunityPost::pending()->count(),
            'soBaoCao' => $soBaoCao,
            'posts' => null,
            'diemBai' => [],
            'binhLuan' => null,
            'hangBaoCao' => null,
        ];

        if ($loc === 'bao-cao') {
            return view('admin.community.index', ['hangBaoCao' => $baoCao->hangCho()] + $chung);
        }

        if ($loc === 'binh-luan') {
            return view('admin.community.index', [
                'binhLuan' => CommunityComment::query()
                    ->with(['user:id,name,email', 'post:id,body', 'parent:id,body'])
                    ->latest()
                    ->paginate(30)
                    ->withQueryString(),
            ] + $chung);
        }

        $query = CommunityPost::query()
            ->with(['user:id,name,email', 'product:id,name,slug', 'media'])
            ->withCount('likers');

        match ($loc) {
            'da-duyet' => $query->whereNotNull('approved_at')->whereNull('hidden_at')->latest('approved_at'),
            'da-an' => $query->whereNotNull('hidden_at')->latest('hidden_at'),
            'tu-choi' => $query->whereNotNull('rejected_at')->latest('rejected_at'),
            default => $query->pending()->oldest('created_at'),
        };

        $posts = $query->paginate(20)->withQueryString();

        return view('admin.community.index', [
            'posts' => $posts,
            'diemBai' => $thuong->daThuong($posts->getCollection()),
        ] + $chung);
    }

    public function approve(Request $request, CommunityPost $post, CommunityReward $thuong): RedirectResponse
    {
        /*
         * Duyệt thì XOÁ dấu từ chối: một bài từng bị từ chối rồi được duyệt lại
         * không được mang cả hai dấu. Một trạng thái phải là một trạng thái.
         */
        $post->approved_at = now();
        $post->rejected_at = null;
        $post->reject_reason = null;
        $post->save();

        $diem = $thuong->thuong($post, $request->boolean('noi_bat'));

        $thongBao = 'Đã duyệt bài của ' . $post->user?->name . '.';

        if ($diem > 0) {
            $thongBao .= ' Cộng ' . $diem . ' điểm cho khách.';
        } elseif ($diem === 0) {
            // Nói ra để người duyệt không tưởng nút "nổi bật" bị hỏng.
            $thongBao .= ' Không cộng điểm: khách đã được thưởng đủ ' . CommunityReward::TOI_DA_MOI_TUAN . ' bài trong tuần này.';
        }

        return back()->with('success', $thongBao);
    }

    public function reject(Request $request, CommunityPost $post): RedirectResponse
    {
        $data = $request->validate([
            // LÝ DO BẮT BUỘC: từ chối im lặng thì khách đăng lại y hệt.
            'reject_reason' => ['required', 'string', 'max:200'],
        ], [], ['reject_reason' => 'lý do']);

        $post->rejected_at = now();
        $post->approved_at = null;
        $post->reject_reason = $data['reject_reason'];
        $post->save();

        return back()->with('success', 'Đã từ chối bài.');
    }

    /** Ẩn / bỏ ẩn một bài đã đăng. Ẩn cần lý do — tác giả đọc được. */
    public function toggleHidden(Request $request, CommunityPost $post): RedirectResponse
    {
        if ($post->hidden_at !== null) {
            $post->forceFill(['hidden_at' => null, 'hidden_reason' => null])->save();

            return back()->with('success', 'Đã hiện lại bài.');
        }

        $data = $request->validate(['hidden_reason' => ['required', 'string', 'max:200']], [], ['hidden_reason' => 'lý do ẩn']);
        $post->forceFill(['hidden_at' => now(), 'hidden_reason' => $data['hidden_reason']])->save();

        return back()->with('success', 'Đã ẩn bài.');
    }

    /** Ẩn / bỏ ẩn một bình luận. Ẩn chứ không xoá: còn dấu vết đã xử lý. */
    public function toggleComment(CommunityComment $comment): RedirectResponse
    {
        $comment->hidden_at = $comment->hidden_at === null ? now() : null;
        $comment->save();

        return back()->with('success', $comment->hidden_at ? 'Đã ẩn bình luận.' : 'Đã hiện lại bình luận.');
    }

    /** Xử lý báo cáo: ẩn nội dung, hoặc kết luận không vi phạm. */
    public function handleReport(Request $request, CommunityReports $baoCao): RedirectResponse
    {
        $data = $request->validate([
            'loai' => ['required', Rule::in([CommunityReport::BAI, CommunityReport::BINH_LUAN])],
            'id' => ['required', 'integer'],
            'ket_qua' => ['required', Rule::in(['an', 'bo-qua'])],
            'ly_do' => [Rule::requiredIf(fn () => $request->input('ket_qua') === 'an' && $request->input('loai') === CommunityReport::BAI), 'nullable', 'string', 'max:200'],
        ], [], ['ly_do' => 'lý do ẩn']);

        if ($data['ket_qua'] === 'an') {
            $baoCao->anNoiDung(Auth::user(), $data['loai'], (int) $data['id'], (string) ($data['ly_do'] ?? 'Vi phạm quy tắc Góc cây'));

            return back()->with('success', 'Đã ẩn nội dung và đóng các báo cáo của nó.');
        }

        $baoCao->boQua(Auth::user(), $data['loai'], (int) $data['id']);

        return back()->with('success', 'Đã đánh dấu không vi phạm.');
    }

    public function destroy(CommunityPost $post, CommunityMediaStore $kho, CommunityReports $baoCao): RedirectResponse
    {
        /*
         * XOÁ THẬT, kèm ảnh / video: bài ở đây là nội dung cá nhân của khách —
         * admin xoá thường là vì nó KHÔNG NÊN tồn tại trên máy chủ.
         */
        DB::transaction(function () use ($post, $kho, $baoCao) {
            $kho->xoaCuaBai($post);
            $baoCao->donCuaBai($post);
            $post->delete();
        });

        return back()->with('success', 'Đã xoá bài khỏi hệ thống.');
    }
}
