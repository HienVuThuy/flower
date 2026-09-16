<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Services\Community\CommunityMediaStore;
use App\Services\Community\CommunityReports;
use App\Services\Notification\NotificationCenter;
use App\Services\Points\CommunityReward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Duyệt bài "Góc cây của bạn" và xử lý báo cáo. */
class CommunityModerationController extends Controller
{
    public function index(Request $request, CommunityReward $thuong, CommunityReports $baoCao): View
    {
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
        $post->approved_at = now();
        $post->rejected_at = null;
        $post->reject_reason = null;
        $post->save();

        $diem = $thuong->thuong($post, $request->boolean('noi_bat'));

        app(NotificationCenter::class)->baiDuocDuyet($post);

        $thongBao = 'Đã duyệt bài của ' . $post->user?->name . '.';

        if ($diem > 0) {
            $thongBao .= ' Cộng ' . $diem . ' điểm cho khách.';
        } elseif ($diem === 0) {
            $thongBao .= ' Không cộng điểm: khách đã được thưởng đủ ' . CommunityReward::TOI_DA_MOI_TUAN . ' bài trong tuần này.';
        }

        return back()->with('success', $thongBao);
    }

    public function reject(Request $request, CommunityPost $post): RedirectResponse
    {
        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'max:200'],
        ], [], ['reject_reason' => 'lý do']);

        $post->rejected_at = now();
        $post->approved_at = null;
        $post->reject_reason = $data['reject_reason'];
        $post->save();

        app(NotificationCenter::class)->baiTuChoi($post, $data['reject_reason']);

        return back()->with('success', 'Đã từ chối bài.');
    }

    public function toggleHidden(Request $request, CommunityPost $post): RedirectResponse
    {
        if ($post->hidden_at !== null) {
            $post->forceFill(['hidden_at' => null, 'hidden_reason' => null])->save();

            return back()->with('success', 'Đã hiện lại bài.');
        }

        $data = $request->validate(['hidden_reason' => ['required', 'string', 'max:200']], [], ['hidden_reason' => 'lý do ẩn']);
        $post->forceFill(['hidden_at' => now(), 'hidden_reason' => $data['hidden_reason']])->save();

        app(NotificationCenter::class)->baiBiAn($post, $data['hidden_reason']);

        return back()->with('success', 'Đã ẩn bài.');
    }

    public function toggleComment(CommunityComment $comment): RedirectResponse
    {
        $comment->hidden_at = $comment->hidden_at === null ? now() : null;
        $comment->save();

        return back()->with('success', $comment->hidden_at ? 'Đã ẩn bình luận.' : 'Đã hiện lại bình luận.');
    }

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
        DB::transaction(function () use ($post, $kho, $baoCao) {
            $kho->xoaCuaBai($post);
            $baoCao->donCuaBai($post);
            $post->delete();
        });

        return back()->with('success', 'Đã xoá bài khỏi hệ thống.');
    }
}
