<?php

namespace Tests\Feature\Coupon;

use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\User;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Luật của mã giảm giá — nơi DUY NHẤT quyết định giảm bao nhiêu.
 * ============================================================
 * Phần này chạm CƠ SỞ DỮ LIỆU (đếm lượt dùng theo tài khoản), nên xếp
 * vào Feature chứ không phải Unit: gọi nó là kiểm thử đơn vị trong khi
 * nó cần bảng `coupon_user` là tự lừa mình về thứ đang được kiểm.
 */
class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $coupons;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coupons = app(CouponService::class);
    }

    #[Test]
    public function giam_theo_phan_tram(): void
    {
        $coupon = Coupon::factory()->percent('10')->create();

        $this->assertSame('50000.00', $this->coupons->discountFor($coupon, '500000.00'));
    }

    #[Test]
    public function giam_theo_phan_tram_bi_kep_boi_muc_toi_da(): void
    {
        // "Giảm 30%" mà không kẹp trần thì một đơn 10 triệu ăn mất 3
        // triệu của cửa hàng. max_discount_amount là cái phanh đó.
        $coupon = Coupon::factory()->percent('30', '120000.00')->create();

        $this->assertSame('120000.00', $this->coupons->discountFor($coupon, '1000000.00'));
        $this->assertSame('60000.00', $this->coupons->discountFor($coupon, '200000.00'));
    }

    #[Test]
    public function giam_so_tien_co_dinh_khong_vuot_qua_tien_hang(): void
    {
        // Nếu không kẹp thì đơn 30.000 với mã giảm 50.000 ra tổng ÂM —
        // cửa hàng trả tiền cho khách để họ mua hàng.
        $coupon = Coupon::factory()->fixed('50000.00')->create();

        $this->assertSame('30000.00', $this->coupons->discountFor($coupon, '30000.00'));
    }

    #[Test]
    public function ma_khong_ton_tai_bi_tu_choi(): void
    {
        $this->expectException(CouponException::class);
        $this->coupons->resolve('KHONGCOTHAT', '500000.00');
    }

    #[Test]
    public function ma_het_han_bi_tu_choi(): void
    {
        Coupon::factory()->expired()->create(['code' => 'HETHAN']);

        $this->expectException(CouponException::class);
        $this->coupons->resolve('HETHAN', '500000.00');
    }

    #[Test]
    public function ma_chua_du_gia_tri_don_bi_tu_choi_kem_con_so_cu_the(): void
    {
        Coupon::factory()->fixed('50000.00')->minOrder('300000.00')->create(['code' => 'DONTU300K']);

        try {
            $this->coupons->resolve('DONTU300K', '200000.00');
            $this->fail('Đáng lẽ phải từ chối.');
        } catch (CouponException $e) {
            // Nói rõ con số, không nói chung chung "mã không hợp lệ":
            // khách phải biết mua thêm bao nhiêu nữa là được giảm.
            $this->assertStringContainsString('300.000', $e->getMessage());
        }
    }

    #[Test]
    public function ma_khong_phan_biet_chu_hoa_chu_thuong_va_khoang_trang(): void
    {
        Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']);

        $this->assertSame('GIAM50K', $this->coupons->resolve('  giam50k  ', '500000.00')->code);
    }

    #[Test]
    public function het_luot_toan_he_thong_thi_bi_tu_choi(): void
    {
        $coupon = Coupon::factory()->fixed('50000.00')->create([
            'code' => 'HETLUOT',
            'usage_limit' => 5,
        ]);
        DB::table('coupons')->where('id', $coupon->id)->update(['used_count' => 5]);

        $this->expectException(CouponException::class);
        $this->coupons->resolve('HETLUOT', '500000.00');
    }

    #[Test]
    public function khach_da_dung_het_suat_rieng_thi_bi_tu_choi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $coupon = Coupon::factory()->fixed('50000.00')->create([
            'code' => 'MOINGUOI1LAN',
            'per_user_limit' => 1,
        ]);

        DB::table('coupon_user')->insert([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'claimed_at' => now(),
            'used_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(CouponException::class);
        $this->coupons->resolve('MOINGUOI1LAN', '500000.00');
    }

    #[Test]
    public function khach_vang_lai_khong_bi_chan_boi_gioi_han_moi_tai_khoan(): void
    {
        // Không có tài khoản thì không có cách nào đếm. Đó là giới hạn
        // THẬT của việc cho đặt hàng không cần đăng nhập, không phải chỗ
        // để giả vờ đã kiểm soát được.
        Coupon::factory()->fixed('50000.00')->create([
            'code' => 'MOINGUOI1LAN',
            'per_user_limit' => 1,
        ]);

        $this->assertSame('MOINGUOI1LAN', $this->coupons->resolve('MOINGUOI1LAN', '500000.00')->code);
    }

    #[Test]
    public function gioi_han_hinh_thuc_thanh_toan_chi_kiem_khi_noi_goi_biet(): void
    {
        // Lúc khách bấm "Áp dụng" thì họ chưa chắc đã chọn xong hình thức
        // thanh toán, nên chặn ở đó là chặn oan. Nơi bắt buộc phải kiểm
        // là lúc GHI ĐƠN.
        Coupon::factory()->fixed('50000.00')->create([
            'code' => 'CHIMOMO',

            /*
             * Một hình thức KHÔNG CÓ trong PaymentMethod.
             *
             * Đây không phải dữ liệu bịa cho vui: đúng tình huống của
             * những mã đã tạo từ trước cho hình thức "chuyển khoản ngân
             * hàng", sau khi hình thức đó bị gỡ khỏi hệ thống. Và nó sẽ
             * lặp lại y hệt với mọi cổng thanh toán bị gỡ về sau.
             */
            'payment_methods' => ['momo'],
        ]);

        // Chưa biết hình thức -> cho qua.
        $this->assertSame(
            'CHIMOMO',
            $this->coupons->resolve('CHIMOMO', '500000.00')->code,
        );

        // Đã biết và sai -> chặn.
        $this->expectException(CouponException::class);
        $this->coupons->resolve('CHIMOMO', '500000.00', PaymentMethod::Cod);
    }

    #[Test]
    public function ma_gioi_han_vao_hinh_thuc_da_bi_go_thi_khong_dung_duoc_voi_hinh_thuc_nao(): void
    {
        /*
         * LỖI ĐÃ SỬA, và nó là lỗi về TIỀN.
         *
         * Coupon::allowedPaymentMethods() lọc bỏ những giá trị không còn
         * là case của enum. Bản trước coi "danh sách sau khi lọc rỗng"
         * đồng nghĩa với "không khai giới hạn nào", nên một mã chỉ dành
         * cho đơn trả trước bỗng áp dụng được cho MỌI đơn ngay khi hình
         * thức đó bị gỡ — nới lỏng điều kiện giảm giá, âm thầm.
         *
         * Đúng là: có khai giới hạn mà không giá trị nào còn hiệu lực
         * thì mã không dùng được với hình thức nào cả.
         */
        $coupon = Coupon::factory()->fixed('50000.00')->create([
            'code' => 'MACU',
            'payment_methods' => ['bank_transfer'],
        ]);

        $this->assertTrue($coupon->hasPaymentRestriction());
        $this->assertSame([], $coupon->allowedPaymentMethods());
        $this->assertFalse($coupon->acceptsPayment(PaymentMethod::Cod));
    }

    #[Test]
    public function loi_bao_cho_ma_gioi_han_vao_hinh_thuc_da_bi_go_phai_doc_duoc(): void
    {
        // Không có hàm nào thay thế được câu này: "chỉ áp dụng khi thanh
        // toán bằng: ." là câu mà cả khách lẫn admin đều không hiểu.
        Coupon::factory()->fixed('50000.00')->create([
            'code' => 'MACU2',
            'payment_methods' => ['bank_transfer'],
        ]);

        try {
            $this->coupons->resolve('MACU2', '500000.00', PaymentMethod::Cod);
            $this->fail('Đáng lẽ phải ném CouponException.');
        } catch (CouponException $e) {
            $this->assertStringContainsString('không còn được hỗ trợ', $e->getMessage());
            $this->assertStringNotContainsString('bằng: .', $e->getMessage());
        }
    }

    #[Test]
    public function reason_unusable_noi_dung_y_het_check(): void
    {
        // Danh sách "Chọn mã giảm giá" đọc lý do từ reasonUnusable(), còn
        // nút bấm đi qua resolve(). Hai đường phải cho cùng một câu trả
        // lời, nếu không danh sách bảo "dùng được" mà nút trả về lỗi.
        $coupon = Coupon::factory()->fixed('50000.00')->minOrder('300000.00')->create(['code' => 'DONTU300K']);

        $this->assertNull($this->coupons->reasonUnusable($coupon, '500000.00'));

        $lyDo = $this->coupons->reasonUnusable($coupon, '200000.00');
        $this->assertNotNull($lyDo);

        try {
            $this->coupons->resolve('DONTU300K', '200000.00');
            $this->fail('Đáng lẽ phải từ chối.');
        } catch (CouponException $e) {
            $this->assertSame($e->getMessage(), $lyDo);
        }
    }
}
