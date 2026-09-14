<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Services\Media\ImageStore;
use App\Services\Points\CommunityReward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Duyệt bài "Góc cây của bạn".
 * ============================================================
 * DUYỆT TRƯỚC KHI HIỆN, KHÔNG DUYỆT SAU.
 *
 * Đây là nội dung người lạ đăng lên một trang bán hàng. Hiện ngay rồi gỡ
 * sau nghĩa là trong khoảng giữa hai việc đó, trang của cửa hàng đang
 * hiển thị bất kỳ thứ gì ai đó vừa gửi lên — kể cả lúc 2 giờ sáng khi
 * không ai trực.
 *
 * Với một cửa hàng nhỏ, số bài mỗi ngày đếm trên đầu ngón tay nên duyệt
 * tay không phải gánh nặng. Khi nào nhiều tới mức không duyệt xuể thì đó
 * là lúc bàn tới tự động — không phải bây giờ.
 *
 * DUYỆT LÀ LÚC THƯỞNG ĐIỂM — xem CommunityReward. Người duyệt vừa đọc bài,
 * nên cũng là người chấm "bài nổi bật".
 */
class CommunityModerationController extends Controller
{
    public function index(Request $request, CommunityReward $thuong): View
    {
        /*
         * MẶC ĐỊNH MỞ Ở TAB "CHỜ DUYỆT".
         *
         * Đó là việc admin vào đây để làm. Mở ở danh sách tất cả thì họ
         * phải tự lọc mỗi lần, và bài chờ lẫn giữa hàng trăm bài cũ.
         */
        $loc = $request->query('loc', 'cho-duyet');

        $query = CommunityPost::query()->with(['user:id,name,email', 'product:id,name,slug']);

        match ($loc) {
            'da-duyet' => $query->whereNotNull('approved_at')->latest('approved_at'),
            'tu-choi' => $query->whereNotNull('rejected_at')->latest('rejected_at'),
            default => $query->pending()->oldest('created_at'),
        };

        $posts = $query->paginate(20)->withQueryString();

        return view('admin.community.index', [
            'posts' => $posts,
            'loc' => $loc,
            // Đếm để tab "Chờ duyệt" mang luôn con số — admin biết còn
            // việc mà không phải bấm vào xem.
            'soChoDuyet' => CommunityPost::pending()->count(),
            'diemBai' => $thuong->daThuong($posts->getCollection()),
        ]);
    }

    public function approve(Request $request, CommunityPost $post, CommunityReward $thuong): RedirectResponse
    {
        /*
         * Duyệt thì XOÁ dấu từ chối.
         *
         * Không xoá thì một bài từng bị từ chối rồi được duyệt lại sẽ
         * mang cả hai dấu, và `statusText()` phải đoán xem cái nào mới
         * hơn. Một trạng thái phải là một trạng thái.
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
            /*
             * LÝ DO BẮT BUỘC.
             *
             * Từ chối im lặng thì khách đăng lại y hệt, rồi lại bị từ
             * chối, và họ kết luận là trang bị hỏng. Bắt buộc một dòng lý
             * do là bắt admin dành mười giây để tiết kiệm cho cả hai bên
             * một vòng lặp.
             */
            'reject_reason' => ['required', 'string', 'max:200'],
        ], [], ['reject_reason' => 'lý do']);

        $post->rejected_at = now();
        $post->approved_at = null;
        $post->reject_reason = $data['reject_reason'];
        $post->save();

        return back()->with('success', 'Đã từ chối bài.');
    }

    public function destroy(CommunityPost $post): RedirectResponse
    {
        /*
         * XOÁ THẬT, kèm ảnh.
         *
         * Khác với bài blog (xoá mềm): bài blog là tài sản của cửa hàng
         * và có thể cần khôi phục. Bài ở đây là nội dung cá nhân của
         * khách kèm ảnh nhà họ — admin xoá nó thường là vì nó KHÔNG NÊN
         * tồn tại trên máy chủ, và xoá mềm thì nó vẫn nằm đó.
         */
        if ($post->photo) {
            app(ImageStore::class)->xoa($post->photo);
        }

        $post->delete();

        return back()->with('success', 'Đã xoá bài khỏi hệ thống.');
    }
}
