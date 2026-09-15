<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GiftCampaignKind;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\GiftCampaign;
use App\Models\GiftItem;
use App\Models\MemberTier;
use App\Models\Product;
use App\Services\Time\Gio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Chương trình quà tặng — cả hai nhánh. Xem migration create_gift_tables.
 *
 * XOÁ chỉ khi chưa phát suất nào; đã phát thì chuyển "Kết thúc" — đơn cũ
 * phải còn nói được quà đến từ chương trình nào.
 */
class GiftCampaignController extends Controller
{
    use LogsAdminActivity;

    public function index(): View
    {
        return view('admin.gift-campaigns.index', [
            'chuongTrinh' => GiftCampaign::query()
                ->with(['giftItem:id,name', 'triggerProduct:id,name', 'minMemberTier:id,name'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->form(new GiftCampaign([
            'kind' => GiftCampaignKind::ChuongTrinh,
            'status' => PromotionStatus::Draft,
            'gift_quantity' => 1,
            'trigger_min_quantity' => 1,
        ]));
    }

    public function edit(GiftCampaign $giftCampaign): View
    {
        return $this->form($giftCampaign);
    }

    public function store(Request $request): RedirectResponse
    {
        $ct = GiftCampaign::create($this->duLieu($request));
        $this->audit()->log('gift-campaign.created', 'Tạo chương trình quà: ' . $ct->name, $ct);

        return redirect()->route('admin.gift-campaigns.index')->with('success', 'Đã tạo chương trình quà.');
    }

    public function update(Request $request, GiftCampaign $giftCampaign): RedirectResponse
    {
        $giftCampaign->update($this->duLieu($request, $giftCampaign));
        $this->audit()->log('gift-campaign.updated', 'Sửa chương trình quà: ' . $giftCampaign->name, $giftCampaign);

        return redirect()->route('admin.gift-campaigns.index')->with('success', 'Đã lưu chương trình quà.');
    }

    public function destroy(GiftCampaign $giftCampaign): RedirectResponse
    {
        if ($giftCampaign->used_count > 0) {
            return back()->with('error', 'Chương trình đã phát quà nên không xoá được. Hãy chuyển trạng thái sang "Kết thúc".');
        }

        $this->audit()->log('gift-campaign.deleted', 'Xoá chương trình quà: ' . $giftCampaign->name, $giftCampaign);
        $giftCampaign->delete();

        return redirect()->route('admin.gift-campaigns.index')->with('success', 'Đã xoá chương trình quà.');
    }

    private function form(GiftCampaign $ct): View
    {
        return view('admin.gift-campaigns.form', [
            'ct' => $ct,
            'vatPham' => GiftItem::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active']),
            'sanPham' => Product::query()->orderBy('name')->get(['id', 'name']),
            'cacHang' => MemberTier::query()->orderBy('min_spend')->get(['id', 'name']),
        ]);
    }

    /** @return array<string, mixed> */
    private function duLieu(Request $request, ?GiftCampaign $dangSua = null): array
    {
        // Ô giờ `datetime-local` là giờ Việt Nam — đổi về giờ lưu trước khi kiểm (xem Gio::doiONhap).
        $request->merge(Gio::doiONhap($request->all(), 'starts_at', 'ends_at'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(GiftCampaignKind::class)],
            'gift_item_id' => ['required', 'integer', 'exists:gift_items,id'],
            'gift_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'trigger_product_id' => [
                Rule::requiredIf($request->input('kind') === GiftCampaignKind::KemSanPham->value),
                'nullable', 'integer', 'exists:products,id',
            ],
            'trigger_min_quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'min_member_tier_id' => ['nullable', 'integer', 'exists:member_tiers,id'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'total_limit' => ['nullable', 'integer', 'min:' . max(1, (int) $dangSua?->used_count), 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::enum(PromotionStatus::class)],
        ], [
            'trigger_product_id.required' => 'Quà tặng kèm phải chọn sản phẩm khách cần mua.',
            'total_limit.min' => 'Tổng suất không được nhỏ hơn số quà đã phát.',
        ], [
            'name' => 'tên chương trình',
            'kind' => 'loại chương trình',
            'gift_item_id' => 'quà',
            'gift_quantity' => 'số lượng quà mỗi đơn',
            'trigger_product_id' => 'sản phẩm cần mua',
            'trigger_min_quantity' => 'số lượng cần mua',
            'min_order_amount' => 'đơn tối thiểu',
            'min_member_tier_id' => 'hạng thành viên',
            'per_user_limit' => 'giới hạn mỗi tài khoản',
            'total_limit' => 'tổng số suất',
            'starts_at' => 'thời gian bắt đầu',
            'ends_at' => 'thời gian kết thúc',
            'status' => 'trạng thái',
        ]);

        $data['name'] = trim($data['name']);
        $data['first_order_only'] = $request->boolean('first_order_only');

        // Chương trình không kèm sản phẩm thì không giữ sản phẩm kích hoạt còn sót từ lần sửa trước.
        if ($data['kind'] === GiftCampaignKind::ChuongTrinh->value) {
            $data['trigger_product_id'] = null;
            $data['trigger_min_quantity'] = 1;
        }

        return $data;
    }
}
