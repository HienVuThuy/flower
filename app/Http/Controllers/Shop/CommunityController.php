<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\Product;
use App\Services\Media\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Góc cây của bạn" — mạng xã hội nhẹ, cố ý nhẹ.
 * ============================================================
 * ⚠️ HAI LUẬT KHÔNG ĐƯỢC NỚI:
 *
 *   1. CHỈ BÀI ĐÃ DUYỆT MỚI HIỆN RA NGOÀI. Đây là nội dung người lạ đăng
 *      lên trang bán hàng; hiện ngay rồi gỡ sau nghĩa là trong khoảng
 *      giữa hai việc đó, cửa hàng đang hiển thị bất kỳ thứ gì vừa được
 *      gửi lên.
 *
 *   2. ẢNH PHẢI ĐI QUA `ImageStore::luu()`. Ảnh chụp bằng điện thoại
 *      mang theo toạ độ GPS chính xác tới vài mét — địa chỉ nhà người
 *      chụp. `ImageStore` tước metadata cho mọi ảnh, không có cờ tắt.
 *      Gọi thẳng `$file->store()` ở đây là bỏ qua đúng lớp bảo vệ đó.
 */
class CommunityController extends Controller
{
    private const MOI_TRANG = 12;

    public function index(Request $request): View
    {
        $posts = CommunityPost::query()
            ->approved()
            ->with(['user:id,name', 'product:id,name,slug,main_image'])
            ->latest('approved_at')
            ->paginate(self::MOI_TRANG);

        return view('shop.community.index', [
            'posts' => $posts,

            /*
             * Bài của chính mình — KỂ CẢ bài chưa duyệt.
             *
             * Người vừa gửi bài phải thấy nó ở đâu đó, kèm trạng thái.
             * Gửi xong mà màn hình không đổi gì thì họ tưởng hỏng và gửi
             * lại — rồi admin có ba bài giống hệt để duyệt.
             */
            'cuaToi' => Auth::check()
                ? CommunityPost::where('user_id', Auth::id())
                    ->with('product:id,name,slug')
                    ->latest()
                    ->limit(5)
                    ->get()
                : collect(),

            'cayDaMua' => Auth::check() ? $this->cayDaMua() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:1000'],
            'photo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:6144'],

            /*
             * CHỈ GẮN ĐƯỢC CÂY ĐÃ MUA — cùng luật với nhật ký (QĐ-129).
             *
             * Cho gắn sản phẩm bất kỳ thì mục này thành chỗ dựng bằng
             * chứng giả về việc đã mua hàng, ngay cạnh chính sản phẩm đó.
             */
            'product_id' => ['nullable', 'integer', Rule::in($this->cayDaMua()->pluck('id')->all())],
        ], [], [
            'body' => 'nội dung',
            'photo' => 'ảnh',
            'product_id' => 'cây',
        ]);

        $post = new CommunityPost([
            'body' => $data['body'],
            'product_id' => $data['product_id'] ?? null,
            'photo' => $request->hasFile('photo')
                // Đi qua ImageStore để metadata bị tước — xem chú thích
                // đầu lớp. Đây là ảnh sẽ hiện công khai.
                ? app(ImageStore::class)->luu($request->file('photo'), 'community')
                : null,
        ]);

        $post->user_id = Auth::id();
        $post->save();

        return back()->with(
            'success',
            'Đã gửi bài. Cửa hàng sẽ duyệt trước khi đăng — thường trong ngày.',
        );
    }

    public function destroy(int $post): RedirectResponse
    {
        /*
         * Lọc theo `user_id` NGAY TRONG TRUY VẤN rồi mới findOrFail —
         * cùng cách đã dùng cho nhật ký (QĐ-123).
         *
         * 404 chứ không 403: 403 xác nhận bài đó có tồn tại.
         */
        $bai = CommunityPost::where('user_id', Auth::id())->findOrFail($post);

        // Ảnh là tệp trên đĩa; khoá ngoại không với tới được.
        if ($bai->photo) {
            app(ImageStore::class)->xoa($bai->photo);
        }

        $bai->delete();

        return back()->with('success', 'Đã xoá bài của bạn.');
    }

    /**
     * Những cây người này ĐÃ MUA.
     *
     * Lấy từ đơn hàng thật, không lấy từ giỏ — giống hệt `cayDaMua()` của
     * nhật ký. Hai chỗ dùng chung một định nghĩa "cây của tôi".
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function cayDaMua(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->whereHas('orderItems.order', fn ($q) => $q->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'main_image']);
    }
}
