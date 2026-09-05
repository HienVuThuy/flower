<?php

namespace Tests\Feature\Shipping;

use App\Services\Shipping\GHNService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lớp nối tới Giao Hàng Nhanh.
 * ============================================================
 * DÙNG Http::fake(), KHÔNG GỌI GHN THẬT.
 *
 * Bài kiểm tra gọi ra dịch vụ ngoài là bài kiểm tra hỏng theo mạng, hỏng
 * theo hạn mức của bên kia, và hỏng vào đúng lúc người ta cần nó nhất.
 * Đã gặp đúng chuyện đó khi dựng tính năng này: sau vài chục lời gọi,
 * cổng thử của GHN reset kết nối và mọi phép đo đều vô nghĩa.
 *
 * Phần "GHN có trả lời đúng không" đã được kiểm bằng tay với dữ liệu
 * thật (65 tỉnh, 30 quận của Hà Nội, 13 phường của Bắc Từ Liêm, cước
 * 42.900₫ nội thành). Phần cần canh lâu dài là CÁCH TA XỬ LÝ câu trả
 * lời — và đó là thứ các bài dưới đây kiểm.
 */
class GhnServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn.base_url', 'https://ghn.test/api');
        config()->set('services.ghn.token', 'token-thu');
        config()->set('services.ghn.shop_id', 1234);
        config()->set('services.ghn.from_district_id', 1482);

        Cache::flush();
    }

    private function ghn(): GHNService
    {
        return app(GHNService::class);
    }

    // ================================================================
    // Lọc bản ghi rác trong danh mục tỉnh
    // ================================================================

    #[Test]
    public function loai_bo_ban_ghi_rac_cua_moi_truong_thu(): void
    {
        /*
         * Cổng thử của GHN lẫn bản ghi rác vào danh mục thật, và KHÔNG
         * có cờ nào phân biệt — cả ba đều `Status = 1`:
         *
         *     2002  "Hà Nội 02"                  → 0 quận/huyện
         *     298   "Test - Alert - Tỉnh - 001"  → 0 quận/huyện
         *     201   "Hà Nội"                     → 30 quận/huyện
         *
         * Hai mục tên gần giống nhau nằm cạnh nhau trong danh sách thả
         * xuống; chọn nhầm là chuyện gần như chắc chắn xảy ra.
         */
        config()->set('services.ghn.skip_province_ids', [2002, 298]);

        Http::fake([
            '*/master-data/province' => Http::response([
                'code' => 200,
                'data' => [
                    ['ProvinceID' => 2002, 'ProvinceName' => 'Hà Nội 02', 'Status' => 1],
                    ['ProvinceID' => 201, 'ProvinceName' => 'Hà Nội', 'Status' => 1],
                    ['ProvinceID' => 298, 'ProvinceName' => 'Test - Alert - Tỉnh - 001', 'Status' => 1],
                ],
            ]),
        ]);

        $ten = collect($this->ghn()->getProvinces()['data'])->pluck('ProvinceName')->all();

        $this->assertSame(['Hà Nội'], $ten);
    }

    #[Test]
    public function KHONG_goi_them_gi_chi_de_loc_danh_muc(): void
    {
        /*
         * BÀI NÀY CANH MỘT SAI LẦM ĐÃ MẮC PHẢI.
         *
         * Bản đầu lọc bằng cách gọi `/master-data/district` không kèm
         * tham số để lấy toàn bộ 727 quận/huyện rồi suy ra tập tỉnh hợp
         * lệ. Nghe hợp lý, nhưng đo trên GHN thật thì lời gọi đó trả về
         * nhỏ giọt và KHÔNG BAO GIỜ XONG — 40 giây vẫn còn đang nhận dữ
         * liệu rồi hết giờ.
         *
         * Nó nằm ngay trên đường mở trang thanh toán, nên mỗi khách phải
         * chờ ngần ấy giây cho một việc chỉ để giấu hai dòng rác.
         *
         * Một lời gọi là đủ. Nếu bài này ngã, nghĩa là ai đó vừa thêm
         * lại một lời gọi phụ vào đường nóng.
         */
        config()->set('services.ghn.skip_province_ids', [2002]);

        Http::fake(['*' => Http::response(['code' => 200, 'data' => []])]);

        $this->ghn()->getProvinces();

        Http::assertSentCount(1);
    }

    // ================================================================
    // Đệm và tạm nghỉ khi hỏng
    // ================================================================

    #[Test]
    public function goi_thanh_cong_thi_dem_lai_khong_goi_lai(): void
    {
        Http::fake([
            '*/master-data/province' => Http::response(['code' => 200, 'data' => []]),
            '*/master-data/district' => Http::response(['code' => 200, 'data' => []]),
        ]);

        $this->ghn()->getProvinces();
        $this->ghn()->getProvinces();
        $this->ghn()->getProvinces();

        // Danh mục địa giới gần như không đổi; gọi ra ngoài ở mỗi lần mở
        // trang thanh toán là chậm cho khách và tốn hạn mức cửa hàng.
        Http::assertSentCount(1);
    }

    #[Test]
    public function dang_hong_thi_tam_nghi_khong_goi_lai_ngay(): void
    {
        /*
         * ĐO ĐƯỢC TRƯỚC KHI SỬA: khi GHN không nhận kết nối, mỗi lần mở
         * trang thanh toán là một lần chờ hết 15 giây rồi mới hỏng — và
         * trang gọi HAI đường, nên khách nhìn màn hình trắng 30 giây cho
         * một dịch vụ chỉ phụ trách phần phí giao.
         *
         * Sau khi sửa, đo lại: lần đầu 10,1s, lần sau 0,0s.
         */
        Http::fake(['*' => Http::response(['code' => 500], 500)]);

        $this->ghn()->getProvinces();
        $soLanDau = count(Http::recorded());

        $this->ghn()->getProvinces();

        $this->assertSame(
            $soLanDau,
            count(Http::recorded()),
            'Vừa hỏng thì không được gọi lại ngay.',
        );
    }

    #[Test]
    public function KHONG_dem_cau_tra_loi_loi_lau_dai(): void
    {
        /*
         * Mặt còn lại của bài trên. Đệm câu trả lời lỗi 24 giờ như câu
         * trả lời đúng là biến một trục trặc mười giây thành một trục
         * trặc một ngày: GHN hồi phục rồi mà trang vẫn báo "không tải
         * được tỉnh/thành".
         */
        /*
         * MỘT stub duy nhất, đổi câu trả lời theo cờ.
         *
         * Không gọi Http::fake() lần thứ hai: nó THÊM stub chứ không
         * thay, và stub đăng ký trước khớp trước — nên bản 500 cũ vẫn
         * trả lời, và bài kiểm tra đo nhầm chính cái giả lập của mình.
         * (Bản đầu của bài này mắc đúng lỗi đó.)
         */
        $ghnDaSong = false;

        Http::fake(function () use (&$ghnDaSong) {
            if (! $ghnDaSong) {
                return Http::response(['code' => 500], 500);
            }

            return Http::response([
                'code' => 200,
                'data' => [
                    ['ProvinceID' => 201, 'ProvinceName' => 'Hà Nội', 'DistrictID' => 1482],
                ],
            ]);
        });

        // GHN trả HTTP 500 thì get() giữ nguyên mã đó, không đổi thành -1
        // (mã -1 dành riêng cho lỗi KHÔNG KẾT NỐI ĐƯỢC). Điều cần canh ở
        // đây chỉ là "không phải 200".
        $this->assertNotSame(200, $this->ghn()->getProvinces()['code'], 'Lúc đầu GHN hỏng.');

        // GHN sống lại, và thời gian tạm nghỉ đã hết.
        $ghnDaSong = true;
        $this->travel(61)->seconds();

        $this->assertSame(
            200,
            $this->ghn()->getProvinces()['code'],
            'GHN hồi phục thì phải dùng được ngay, không đợi hết 24 giờ đệm.',
        );
    }

    // ================================================================
    // Chưa cấu hình thì không gọi ra ngoài
    // ================================================================

    #[Test]
    public function chua_khai_token_thi_khong_goi_ra_ngoai(): void
    {
        // Gọi mà thiếu token chỉ nhận về 401 kèm một dòng log khó hiểu.
        // Trả lời ngay tại chỗ cho người dựng hệ thống biết sửa ở đâu.
        config()->set('services.ghn.token', '');
        Http::fake();

        $this->assertSame(-1, $this->ghn()->getProvinces()['code']);

        Http::assertNothingSent();
    }

    // ================================================================
    // Thông số kiện hàng
    // ================================================================

    #[Test]
    public function thong_so_kien_hang_dung_chung_cho_bao_gia_va_van_don(): void
    {
        /*
         * Báo giá bằng một bộ kích thước rồi tạo vận đơn bằng bộ khác
         * thì con số khách đã trả và con số GHN thu của cửa hàng lệch
         * nhau — phần chênh cửa hàng chịu, và không ai phát hiện cho tới
         * lúc đối soát cuối tháng.
         */
        $goi = $this->ghn()->packageParameters(750);

        $this->assertSame(750, $goi['weight']);
        $this->assertSame((int) config('services.ghn.box.length'), $goi['length']);
        $this->assertSame((int) config('services.ghn.service_type_id'), $goi['service_type_id']);
    }

    #[Test]
    public function khoi_luong_khong_bao_gio_bang_khong(): void
    {
        // GHN từ chối thẳng đơn có khối lượng 0.
        $this->assertGreaterThan(0, $this->ghn()->packageParameters(0)['weight']);
    }
}
