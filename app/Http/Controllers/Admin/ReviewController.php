<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkAction;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kiểm duyệt đánh giá.
 *
 * Cửa hàng KHÔNG duyệt trước từng bài — đánh giá hiện ngay khi khách gửi
 * (xem chú thích ở migration). Màn hình này để xử lý bài có vấn đề: nội
 * dung bậy, spam, hoặc nhầm sản phẩm.
 *
 * Ẩn thay vì xoá: giữ lại bản ghi thì còn dấu vết đã xử lý gì, và nếu ẩn
 * nhầm thì bật lại được. Xoá hẳn chỉ dành cho khách tự gỡ bài của mình.
 */
class ReviewController extends Controller
{
    use HandlesBulkAction;
    use LogsAdminActivity;

    public function index(Request $request): View
    {
        $filter = $request->query('trang_thai');

        $reviews = Review::query()
            // Thiếu hai dòng này là mỗi bài thêm hai truy vấn.
            ->with(['product', 'user'])
            ->when($filter === 'an', fn ($q) => $q->where('is_visible', false))
            ->when($filter === 'hien', fn ($q) => $q->where('is_visible', true))

            /*
             * LỌC THEO SỐ SAO — việc admin cần nhất ở trang này.
             *
             * Đánh giá 1-2 sao là lời phàn nàn cần trả lời, và chúng lẫn
             * giữa hàng chục đánh giá 5 sao. Không lọc được thì admin
             * phải lật từng trang để tìm.
             */
            /*
             * is_numeric CHỨ KHÔNG PHẢI filled().
             *
             * Ô này nhận hai loại giá trị: một con số ("5") hoặc từ khoá
             * "thap". filled('sao') đúng với CẢ HAI, nên nhánh số cũng
             * chạy khi chọn "thap" và biến thành where('rating', 0) —
             * integer('thap') là 0. Kết quả: lọc "từ 2 sao trở xuống"
             * luôn ra RỖNG, dù có đánh giá 2 sao trong cơ sở dữ liệu.
             */
            ->when(
                is_numeric($request->query('sao')),
                fn ($q) => $q->where('rating', $request->integer('sao'))
            )
            ->when($request->query('sao') === 'thap', fn ($q) => $q->where('rating', '<=', 2))

            /*
             * Tìm trong nội dung nhận xét và TÊN SẢN PHẨM.
             *
             * Tên sản phẩm phải tìm qua whereHas: khách gọi tới kêu "bó
             * hoa hồng bị dập" chứ không đọc mã đánh giá.
             */
            ->when($request->filled('q'), function ($query) use ($request) {
                $tu = trim((string) $request->query('q'));

                $query->where(function ($q) use ($tu) {
                    $q->where('comment', 'like', '%'.$tu.'%')
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$tu.'%'));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'filter' => $filter,
            'hiddenCount' => Review::where('is_visible', false)->count(),
        ]);
    }

    /**
     * Bật/tắt hiển thị một đánh giá.
     *
     * is_visible KHÔNG nằm trong $fillable của model, nên phải gán trực
     * tiếp ở đây — đúng như thiết kế: chỉ mã nguồn phía quản trị mới đổi
     * được cột này, không phải dữ liệu gửi lên từ form của khách.
     */
    /**
     * Cửa hàng trả lời một đánh giá.
     *
     * VÌ SAO CẦN: trước đây admin chỉ làm được đúng một việc với đánh
     * giá — ẨN nó đi. Với một đánh giá 2 sao thì đó là lựa chọn tệ nhất:
     * khách viết ra vì muốn được nghe, ẩn đi là nói rằng cửa hàng không
     * muốn nghe. Và người đọc sau đó chỉ thấy toàn 5 sao nên không tin
     * trang đánh giá nữa.
     *
     * PHẢN HỒI LÀ CÔNG KHAI — khách nào cũng đọc được. Đó là điểm khác
     * hẳn ghi chú nội bộ của đơn hàng, và là lý do nó đáng viết cẩn thận.
     *
     * GỬI Ô TRỐNG = XOÁ PHẢN HỒI. Không cần thêm một route "xoá" riêng
     * cho một thao tác mà biểu mẫu này đã diễn tả được.
     */
    public function reply(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'admin_reply' => ['nullable', 'string', 'max:1000'],
        ], [
            'admin_reply.max' => 'Phản hồi không được vượt quá 1000 ký tự.',
        ], ['admin_reply' => 'phản hồi']);

        $noiDung = trim((string) ($data['admin_reply'] ?? ''));

        /*
         * Gán trực tiếp: hai cột này cố ý nằm ngoài $fillable để không
         * request nào của khách tự viết lời "phản hồi từ cửa hàng" dưới
         * đánh giá của chính mình.
         */
        $review->admin_reply = $noiDung ?: null;

        /*
         * Chỉ đặt mốc thời gian khi THẬT SỰ có nội dung, và giữ nguyên
         * mốc cũ khi admin chỉ sửa lại câu chữ. Đặt lại mỗi lần lưu thì
         * một lần sửa lỗi chính tả biến thành "vừa phản hồi hôm nay" cho
         * câu trả lời từ tháng trước.
         */
        if ($noiDung === '') {
            $review->admin_replied_at = null;
        } elseif ($review->admin_replied_at === null) {
            $review->admin_replied_at = now();
        }

        $review->save();

        /*
         * Phản hồi của cửa hàng là nội dung CÔNG KHAI dưới tên cửa
         * hàng — ai viết nó phải truy được. Ghi cả nội dung vào
         * properties: sửa lại lời đã đăng cũng là một việc đáng ghi.
         */
        $this->audit()->log(
            $noiDung === '' ? 'review.reply_removed' : 'review.replied',
            sprintf(
                '%s phản hồi cho đánh giá #%d',
                $noiDung === '' ? 'Xoá' : 'Lưu',
                $review->id,
            ),
            $review,
            ['noi_dung' => $noiDung],
        );

        return back()->with('success', $noiDung === ''
            ? 'Đã xoá phản hồi.'
            : 'Đã lưu phản hồi của cửa hàng.');
    }

    public function toggle(Review $review): RedirectResponse
    {
        $review->is_visible = ! $review->is_visible;
        $review->save();

        /*
         * Ẩn một đánh giá xấu là thao tác dễ bị nghi ngờ nhất ở khu
         * quản trị, nên cũng là thao tác cần ghi rõ nhất: ai ẩn,
         * lúc nào, đánh giá mấy sao.
         */
        $this->audit()->log(
            $review->is_visible ? 'review.shown' : 'review.hidden',
            sprintf(
                '%s đánh giá #%d (%d sao)',
                $review->is_visible ? 'Hiện lại' : 'Ẩn',
                $review->id,
                (int) $review->rating,
            ),
            $review,
        );

        return back()->with('success', $review->is_visible
            ? 'Đã hiện lại đánh giá.'
            : 'Đã ẩn đánh giá khỏi trang sản phẩm.');
    }

    /**
     * Ẩn hoặc hiện lại nhiều đánh giá một lúc.
     *
     * VÌ SAO CẦN Ở ĐÂY: một đợt bị gửi rác thì có hàng chục đánh giá
     * cùng phải ẩn, và mỗi cái là một lần bấm rồi chờ tải lại trang. Với
     * đúng loại việc đó — nhiều dòng, cùng một thao tác — làm từng cái
     * là cách chắc chắn để bỏ sót.
     *
     * KHÔNG CÓ "XOÁ HÀNG LOẠT", và cũng không có xoá từng cái. Nội dung
     * là của khách; cửa hàng được quyền ẩn khỏi trang bán hàng, không
     * được quyền xoá lời người ta đã viết. Ẩn thì còn lùi lại được, xoá
     * thì không.
     */
    public function bulk(Request $request): RedirectResponse
    {
        ['viec' => $viec, 'ids' => $ids] = $this->validateBulk(
            $request,
            ['an', 'hien'],
            'reviews',
        );

        $hien = $viec === 'hien';

        /*
         * Chỉ ghi những dòng THẬT SỰ đổi: tích cả những đánh giá đang ẩn
         * rồi bấm "Ẩn" thì chúng không đổi gì, và đếm chúng vào là báo
         * một con số lớn hơn việc đã làm.
         */
        $so = Review::whereKey($ids)
            ->where('is_visible', ! $hien)
            ->update(['is_visible' => $hien]);

        if ($so === 0) {
            return back()->with('info', 'Các đánh giá đã chọn vốn đã ở trạng thái đó.');
        }

        $this->audit()->log(
            $hien ? 'review.bulk_shown' : 'review.bulk_hidden',
            sprintf('%s hàng loạt %d đánh giá', $hien ? 'Hiện lại' : 'Ẩn', $so),
            null,
            ['ids' => $ids],
        );

        return back()->with('success', $hien
            ? "Đã hiện lại {$so} đánh giá."
            : "Đã ẩn {$so} đánh giá khỏi trang sản phẩm.");
    }
}