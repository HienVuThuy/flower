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
 * Không tiêu chí nào bắt buộc.
 *
 * ============================================================
 * TRANG NÀY KHÔNG TỰ HIỂN THỊ KẾT QUẢ — nó là CỬA VÀO.
 *
 * Trước đây nó tự lọc và tự đổ kết quả ra, và sáu trong bảy tiêu chí của
 * nó trùng với bộ lọc của trang sản phẩm. Hai bộ máy kết quả cho cùng
 * một câu hỏi: sửa cách xếp hạng ở một nơi thì nơi kia vẫn xếp kiểu cũ,
 * và trang này thiếu hẳn sắp xếp, lọc giá, phân trang.
 *
 * Nay trả lời xong thì chuyển sang `/san-pham` kèm đúng những tham số
 * đó. Cái riêng của trang này được giữ nguyên — CÁCH HỎI, theo ngôn ngữ
 * người mua ("ban công đầy nắng", "tôi hay quên tưới") thay vì một bảng
 * lọc. Cái trùng thì bỏ.
 *
 * Xem QĐ-172.
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

            /*
             * ĐƯỜNG DẪN SANG TRANG KẾT QUẢ.
             * ============================================================
             * Trang này KHÔNG còn tự hiển thị kết quả — xem chú thích đầu
             * lớp và QĐ-172.
             *
             * Chuyển nguyên chuỗi truy vấn sang `/san-pham`: tên tham số
             * của hai trang ĐÃ TRÙNG SẴN (`vi-tri`, `menh`, `kinh-nghiem`
             * và các `queryKey()` của nhãn), nên không cần bảng dịch nào
             * ở giữa. Một bảng dịch là chỗ thứ hai để lệch nhau.
             *
             * Lọc lại qua `$traits` / `$placement` / `$element` đã kiểm
             * chứ không bê thẳng `$request->query()`: tham số lạ trên URL
             * bị bỏ ở đây, không đẩy tiếp sang trang sau.
             */
            'ketQuaUrl' => route('shop.products.index', array_filter([
                'vi-tri' => $placement?->value,
                'menh' => $element?->value,
                'kinh-nghiem' => $difficulty?->value,
            ] + $this->thamSoNhan($traits))),
        ]);
    }

    /**
     * Đổi khoá `TraitType->value` thành tên tham số URL.
     *
     * `$traits` dùng khoá theo giá trị enum (`growth_form`) vì
     * `PlantAdvisor` nhận như vậy, còn URL dùng tên tiếng Việt không dấu
     * (`dang-song`). Một hàm đổi, không phải bốn dòng chép tay.
     *
     * @param  array<string, string>  $traits
     * @return array<string, string>
     */
    private function thamSoNhan(array $traits): array
    {
        $ket = [];

        foreach ($traits as $loai => $giaTri) {
            $type = TraitType::tryFrom((string) $loai);

            if ($type) {
                $ket[$type->queryKey()] = $giaTri;
            }
        }

        return $ket;
    }
}
