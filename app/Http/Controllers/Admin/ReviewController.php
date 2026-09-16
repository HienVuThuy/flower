<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkAction;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Kiểm duyệt đánh giá. */
class ReviewController extends Controller
{
    use HandlesBulkAction;
    use LogsAdminActivity;

    public function index(Request $request): View
    {
        $filter = $request->query('trang_thai');

        $reviews = Review::query()
            ->with(['product', 'user'])
            ->when($filter === 'an', fn ($q) => $q->where('is_visible', false))
            ->when($filter === 'hien', fn ($q) => $q->where('is_visible', true))

            ->when(
                is_numeric($request->query('sao')),
                fn ($q) => $q->where('rating', $request->integer('sao'))
            )
            ->when($request->query('sao') === 'thap', fn ($q) => $q->where('rating', '<=', 2))

            ->when($request->query('tra_loi') === 'chua', fn ($q) => $q->whereNull('admin_reply'))
            ->when($request->query('tra_loi') === 'roi', fn ($q) => $q->whereNotNull('admin_reply'))

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

    public function reply(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'admin_reply' => ['nullable', 'string', 'max:1000'],
        ], [
            'admin_reply.max' => 'Phản hồi không được vượt quá 1000 ký tự.',
        ], ['admin_reply' => 'phản hồi']);

        $noiDung = trim((string) ($data['admin_reply'] ?? ''));

        $review->admin_reply = $noiDung ?: null;

        if ($noiDung === '') {
            $review->admin_replied_at = null;
        } elseif ($review->admin_replied_at === null) {
            $review->admin_replied_at = now();
        }

        $review->save();

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

    public function bulk(Request $request): RedirectResponse
    {
        ['viec' => $viec, 'ids' => $ids] = $this->validateBulk(
            $request,
            ['an', 'hien'],
            'reviews',
        );

        $hien = $viec === 'hien';

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