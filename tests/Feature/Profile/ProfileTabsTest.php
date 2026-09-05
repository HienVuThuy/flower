<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Trang Hồ sơ chia thành ba mục.
 * ============================================================
 * Đo trước khi sửa ở khổ 1440×900: trang cao 3748px — hơn BỐN màn hình
 * cuộn cho một trang cài đặt. Muốn đổi tuỳ chọn nhận thư thì phải cuộn
 * qua toàn bộ biểu mẫu đổi mật khẩu và danh sách thiết bị, mỗi lần.
 *
 * Mỗi mục là một ĐỊA CHỈ RIÊNG chứ không phải tab JavaScript, nên các
 * bài dưới đây canh đúng những điều mà cách làm ấy phải giữ được: gửi
 * được đường dẫn, chịu được tham số lạ, và biểu mẫu lỗi thì quay về
 * đúng mục đang mở.
 */
class ProfileTabsTest extends TestCase
{
    use RefreshDatabase;

    private function khach(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function mac_dinh_mo_muc_thong_tin(): void
    {
        $this->actingAs($this->khach())
            ->get('/tai-khoan')
            ->assertOk()
            ->assertSee('Thông tin cá nhân')
            // Ba khối nặng nhất KHÔNG được dựng ở mục này.
            ->assertDontSee('Thiết bị đang đăng nhập')
            ->assertDontSee('Nền sáng / tối');
    }

    #[Test]
    public function muc_bao_mat_gom_dung_ba_khoi_lien_quan(): void
    {
        /*
         * Ba khối theo đúng thứ tự một người xử lý khi nghi tài khoản bị
         * chiếm: đổi mật khẩu → xem ai đang đăng nhập → nếu tệ quá thì
         * xoá hẳn tài khoản.
         */
        $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=bao-mat')
            ->assertOk()
            ->assertSee('Đổi mật khẩu')
            ->assertSee('Thiết bị đang đăng nhập')
            ->assertSee('Xoá tài khoản')
            ->assertDontSee('Thông tin cá nhân');
    }

    #[Test]
    public function muc_tuy_chon_gom_nen_sang_toi_va_thu_thong_bao(): void
    {
        $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=tuy-chon')
            ->assertOk()
            ->assertSee('Nền sáng / tối')
            ->assertSee('Thư thông báo')
            ->assertDontSee('Đổi mật khẩu');
    }

    #[Test]
    public function tham_so_la_quay_ve_muc_mac_dinh_chu_khong_bao_loi(): void
    {
        /*
         * `?muc=abc` chỉ tới được bằng cách tự gõ hoặc bằng một đường
         * dẫn cũ đã đổi tên. Cả hai đều không đáng để đổ trang lỗi —
         * hiện mục đầu tiên là điều người dùng chờ đợi.
         */
        $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=khong-co-that')
            ->assertOk()
            ->assertSee('Thông tin cá nhân');
    }

    #[Test]
    public function khoi_tom_tat_hien_o_MOI_muc(): void
    {
        /*
         * Đường sang đơn hàng, sổ địa chỉ, ví voucher là thứ người ta
         * thật sự tới trang này để dùng. Nhét nó vào một mục riêng là
         * bắt bấm thêm một lần cho việc phổ biến nhất, để đổi lấy chỗ
         * trống cho những việc hiếm hơn.
         */
        foreach (['thong-tin', 'bao-mat', 'tuy-chon'] as $muc) {
            $this->actingAs($this->khach())
                ->get("/tai-khoan?muc={$muc}")
                ->assertOk()
                ->assertSee(route('shop.orders.index'))
                ->assertSee(route('shop.reviews.mine'));
        }
    }

    #[Test]
    public function bieu_mau_loi_quay_ve_DUNG_muc_dang_mo(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT ở đây.
         *
         * Biểu mẫu đổi mật khẩu nằm ở mục "Bảo mật". Nhập sai mật khẩu
         * hiện tại mà bị ném về mục "Thông tin" thì người dùng mất luôn
         * cả thông báo lỗi lẫn chỗ vừa đứng — họ chỉ thấy trang nhảy về
         * đầu và không hiểu vì sao không có gì xảy ra.
         *
         * Đây chính là thứ một tab JavaScript làm hỏng: sau khi tải lại
         * trang, tab active quay về mặc định.
         */
        $user = $this->khach();

        $this->actingAs($user)
            ->from('/tai-khoan?muc=bao-mat')
            ->put('/tai-khoan/mat-khau', [
                'current_password' => 'sai-be-bet',
                'password' => 'MatKhauMoi@123',
                'password_confirmation' => 'MatKhauMoi@123',
            ])
            ->assertRedirect('/tai-khoan?muc=bao-mat');
    }

    #[Test]
    public function moi_muc_deu_co_the_gui_duong_dan_cho_nguoi_khac(): void
    {
        // Mục là địa chỉ thật, không phải trạng thái trong bộ nhớ trình
        // duyệt — nên mở thẳng đường dẫn là ra đúng mục đó.
        $html = $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=bao-mat')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-current="page"', $html);

        $this->assertMatchesRegularExpression(
            '#href="[^"]*muc=tuy-chon"#',
            $html,
            'Thanh mục phải là liên kết thật để mở tab mới và lưu dấu trang được.',
        );
    }

    #[Test]
    public function khong_con_the_card_long_trong_card(): void
    {
        /*
         * LỖI ĐÃ TỪNG CÓ: bốn khối bị lồng bên trong khối "Đổi mật
         * khẩu" vì một lần chèn thiếu thẻ đóng. HTML vẫn CÂN BẰNG tổng
         * số thẻ nên trình duyệt không kêu gì, và nhìn qua trang vẫn như
         * thường — chỉ có viền và khoảng đệm chồng lên nhau.
         *
         * VÌ SAO KHÔNG ĐẾM THẺ <div>: bản đầu của bài này đếm số thẻ mở
         * và đóng rồi so bằng nhau. Phép đếm đó XANH trên chính bản
         * hỏng — 12 thẻ mở, 12 thẻ đóng — vì thẻ đóng bị thiếu ở giữa
         * chỉ trôi xuống cuối chứ không mất đi. Một bài kiểm tra không
         * bắt được đúng lỗi nó nói mình canh còn tệ hơn không có bài
         * nào, vì nó tạo ra niềm tin sai.
         *
         * Phải hỏi đúng câu hỏi: có thẻ `surface-card` nào nằm TRONG
         * một thẻ `surface-card` khác không.
         */
        foreach (['thong-tin', 'bao-mat', 'tuy-chon'] as $muc) {
            $html = $this->actingAs($this->khach())
                ->get("/tai-khoan?muc={$muc}")
                ->assertOk()
                ->getContent();

            $dom = new \DOMDocument();

            // HTML thật luôn có chỗ libxml chê (thẻ HTML5 nó chưa biết,
            // ký tự &copy; chưa escape...). Tắt báo lỗi rồi tự dọn, chứ
            // không để chúng làm hỏng bài kiểm tra vì một chuyện khác.
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            libxml_clear_errors();

            $long = (new \DOMXPath($dom))->query(
                '//*[contains(@class, "surface-card")]'
                .'//*[contains(@class, "surface-card")]'
            );

            $this->assertSame(
                0,
                $long->length,
                "Mục {$muc}: không được có khối surface-card nào nằm trong khối khác.",
            );
        }
    }
}
