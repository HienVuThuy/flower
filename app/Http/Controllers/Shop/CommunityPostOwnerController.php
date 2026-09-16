<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Services\Community\CommunityException;
use App\Services\Community\PostOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Chủ bài tự quản lý bài của mình. Mỏng: luật nằm ở PostOwner.
 *
 * Mỗi việc là một PATCH riêng chứ không gộp một route "cập nhật bài": ba việc
 * này có ba luật khác nhau (ghim đòi bài đang hiển thị, ẩn thì không), và gộp
 * lại thì phải đọc một tham số "hành động" rồi rẽ nhánh — chỗ dễ quên kiểm.
 */
class CommunityPostOwnerController extends Controller
{
    public function hide(int $post, PostOwner $chuBai): RedirectResponse
    {
        $bai = $chuBai->baiCuaToi(Auth::user(), $post);
        $dangAn = $chuBai->doiAn($bai);

        return back()->with('success', $dangAn
            ? 'Đã tạm ẩn bài. Chỉ mình bạn thấy bài này; bật lại lúc nào cũng được.'
            : 'Đã hiện lại bài.');
    }

    public function pin(int $post, PostOwner $chuBai): RedirectResponse
    {
        $bai = $chuBai->baiCuaToi(Auth::user(), $post);

        try {
            $ghim = $chuBai->doiGhim($bai);
        } catch (CommunityException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ghim
            ? 'Đã ghim bài lên đầu trang cá nhân của bạn.'
            : 'Đã bỏ ghim bài.');
    }

    public function lockComments(int $post, PostOwner $chuBai): RedirectResponse
    {
        $bai = $chuBai->baiCuaToi(Auth::user(), $post);
        $khoa = $chuBai->doiKhoaBinhLuan($bai);

        return back()->with('success', $khoa
            ? 'Đã khoá bình luận cho bài này. Bình luận cũ vẫn còn.'
            : 'Đã mở lại bình luận.');
    }

    public function hideComment(int $comment, PostOwner $chuBai): RedirectResponse
    {
        $bl = CommunityComment::findOrFail($comment);

        try {
            $an = $chuBai->doiAnBinhLuan(Auth::user(), $bl);
        } catch (CommunityException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $an ? 'Đã ẩn bình luận khỏi bài của bạn.' : 'Đã hiện lại bình luận.');
    }
}
