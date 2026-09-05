<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CareDifficulty;
use App\Enums\FengShuiElement;
use App\Enums\Placement;
use App\Enums\TraitType;
use App\Http\Controllers\Controller;
use App\Services\Recommendation\PlantAdvisor;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang "Chọn cây theo nhu cầu".
 * ============================================================
 * TRẢ LỜI CÂU HỎI MÀ TRANG DANH SÁCH KHÔNG TRẢ LỜI ĐƯỢC.
 *
 * Trang sản phẩm sắp xếp theo cách CỬA HÀNG nghĩ về hàng hoá: danh mục,
 * hình thức bán, giá. Người mua cây lần đầu không nghĩ theo trục nào
 * trong đó — họ nghĩ "tôi có cái ban công đầy nắng" hoặc "tôi hay quên
 * tưới". Trang này sắp theo cách KHÁCH nghĩ về vấn đề của họ.
 *
 * BA TIÊU CHÍ ĐỘC LẬP, kết hợp tuỳ ý: vị trí đặt, hợp mệnh, kinh nghiệm.
 * Không tiêu chí nào bắt buộc — vào trang mà chưa chọn gì thì thấy cây
 * dễ chăm, vì đó là câu trả lời hợp lý nhất cho người chưa biết hỏi gì.
 */
class AdvisorController extends Controller
{
    public function __construct(
        private readonly PlantAdvisor $advisor,
    ) {
    }

    public function index(Request $request): View
    {
        /*
         * tryFrom() trả null cho giá trị lạ trên URL — tiêu chí đó coi
         * như không được chọn. KHÔNG abort(404): người ta chép link cho
         * nhau, và một tham số hỏng không đáng để cả trang biến mất.
         */
        $placement = Placement::tryFrom((string) $request->query('vi-tri', ''));
        $element = FengShuiElement::tryFrom((string) $request->query('menh', ''));
        $difficulty = CareDifficulty::tryFrom((string) $request->query('kinh-nghiem', ''));

        /*
         * BỐN TIÊU CHÍ SINH THÁI — đọc bằng MỘT vòng lặp.
         *
         * Danh sách và tên tham số URL đều nằm ở TraitType, nên thêm một
         * tiêu chí mới về sau không phải sửa controller này. Bốn khối
         * if chép tay thì khối thứ năm sẽ quên một dòng.
         *
         * Giá trị lạ bị bỏ qua chứ KHÔNG abort(404): người ta chép link
         * cho nhau, và một tham số hỏng không đáng để cả trang biến mất.
         */
        $loaiSinhThai = [
            TraitType::Habitat,
            TraitType::GrowthForm,
            TraitType::Shape,
            TraitType::Color,
        ];

        $traits = [];

        foreach ($loaiSinhThai as $type) {
            $value = (string) $request->query($type->queryKey(), '');

            if ($value !== '' && array_key_exists($value, $type->options())) {
                $traits[$type->value] = $value;
            }
        }

        $hasFilter = $placement !== null || $element !== null || $difficulty !== null || $traits !== [];

        return view('shop.advisor.index', [
            'placement' => $placement,
            'element' => $element,
            'difficulty' => $difficulty,
            'hasFilter' => $hasFilter,

            'placements' => $this->advisor->availablePlacements(),
            'elements' => $this->advisor->availableElements(),
            'difficulties' => $this->advisor->availableDifficulties(),

            /*
             * Chưa chọn gì thì hiện cây dễ chăm, KHÔNG hiện toàn bộ
             * catalog. Trang này tồn tại để thu hẹp lựa chọn; mở đầu bằng
             * hai mươi sản phẩm là lặp lại đúng vấn đề khách đang gặp.
             */
            /*
             * Danh sách tiêu chí sinh thái dựng SẴN cho view, kèm số
             * lượng — view không tự đi đếm trong vòng lặp.
             */
            'nhomSinhThai' => collect($loaiSinhThai)
                ->map(fn (TraitType $t) => [
                    'type' => $t,
                    'values' => $this->advisor->availableTraitValues($t),
                    'dangChon' => $traits[$t->value] ?? null,
                ])
                ->filter(fn (array $n) => $n['values']->isNotEmpty()),

            'products' => $hasFilter
                ? $this->advisor->suggest($placement, $element, $difficulty, traits: $traits)
                : $this->advisor->forBeginners(),
        ]);
    }
}
