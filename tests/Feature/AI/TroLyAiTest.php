<?php

namespace Tests\Feature\AI;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\ShoppingAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trợ lý AI: chưa có khoá thì không gọi ra ngoài; có khoá thì gọi Gemini đúng cách với dữ liệu cửa hàng thật… */
class TroLyAiTest extends TestCase
{
    use RefreshDatabase;

    private function batAi(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.key' => 'khoa-thu-nghiem',
            'ai.gemini.model' => 'gemini-2.5-flash',
            'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);
    }

    private function giaTraLoi(string $chu = 'Chào bạn, Kim tiền chậu sứ đang còn hàng.', int $ma = 200): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => $ma === 200
                ? Http::response(['candidates' => [['content' => ['parts' => [['text' => $chu]]]]]])
                : Http::response(['error' => ['message' => 'loi']], $ma),
        ]);
    }

    private function noiDungGui(Request $r): string
    {
        return json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function kimTien(): Product
    {
        return Product::factory()->for(Category::factory()->state(['name' => 'Cây để bàn']))
            ->price('250000.00')->stock(7)
            ->create(['name' => 'Kim tiền chậu sứ', 'status' => 'active']);
    }

    #[Test]
    public function chua_co_khoa_thi_khong_goi_ra_ngoai_va_noi_chua_cau_hinh(): void
    {
        config(['ai.gemini.key' => null]);
        Http::fake();

        $this->postJson(route('shop.ai.ask'), ['cau_hoi' => 'Cây nào dễ chăm?'])
            ->assertStatus(503)
            ->assertJson(['loi' => 'Trợ lý AI chưa được cấu hình.']);

        Http::assertNothingSent();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-ai-chua-cau-hinh', $html);
        $this->assertMatchesRegularExpression('#name="cau_hoi"[^>]*disabled#s', $html);
    }

    #[Test]
    public function goi_gemini_voi_khoa_trong_header_va_du_lieu_san_pham_that(): void
    {
        $this->batAi();
        $this->giaTraLoi();
        $this->kimTien();

        $this->postJson(route('shop.ai.ask'), ['cau_hoi' => 'kim tiền giá bao nhiêu'])
            ->assertOk()
            ->assertJson(['tra_loi' => 'Chào bạn, Kim tiền chậu sứ đang còn hàng.']);

        Http::assertSent(function (Request $r) {
            $noiDung = $this->noiDungGui($r);

            return $r->hasHeader('x-goog-api-key', 'khoa-thu-nghiem')
                && ! str_contains($r->url(), 'khoa-thu-nghiem')
                && str_contains($r->url(), 'models/gemini-2.5-flash:generateContent')
                && str_contains($noiDung, 'Kim tiền chậu sứ')
                && str_contains($noiDung, '250.000')
                && str_contains($noiDung, 'còn 7 sản phẩm')
                && str_contains($noiDung, 'Cây để bàn')
                && str_contains($noiDung, 'Chỉ dùng thông tin trong khối DỮ LIỆU CỬA HÀNG');
        });
    }

    #[Test]
    public function lich_su_hoi_dap_gui_kem_va_cau_noi_tiep_van_tim_dung_san_pham(): void
    {
        $this->batAi();
        $this->giaTraLoi('Dạ có ạ.');
        $this->kimTien();

        $this->postJson(route('shop.ai.ask'), ['cau_hoi' => 'kim tiền còn hàng không'])->assertOk();
        $this->postJson(route('shop.ai.ask'), ['cau_hoi' => 'vậy nó hợp đặt đâu?'])->assertOk();

        $lanHai = Http::recorded()[1][0];
        $noiDung = $lanHai->data();

        $this->assertSame(['user', 'model', 'user'], array_column($noiDung['contents'], 'role'));
        $this->assertSame('vậy nó hợp đặt đâu?', $noiDung['contents'][2]['parts'][0]['text']);
        $this->assertStringContainsString('Kim tiền chậu sứ', $this->noiDungGui($lanHai), 'Câu nối tiếp dùng câu hỏi trước để tìm sản phẩm');

        $this->assertCount(4, session(ShoppingAdvisor::SESSION_KEY));

        $this->deleteJson(route('shop.ai.reset'))->assertOk();
        $this->assertNull(session(ShoppingAdvisor::SESSION_KEY));
    }

    #[Test]
    public function du_lieu_ca_nhan_chi_cua_chinh_nguoi_hoi(): void
    {
        $this->batAi();
        $this->giaTraLoi();
        $senDa = Product::factory()->for(Category::factory())->create(['name' => 'Sen đá kim cương bí mật', 'status' => 'active']);

        $a = User::factory()->create();
        DB::table('wishlists')->insert(['user_id' => $a->id, 'product_id' => $senDa->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($a)->postJson(route('shop.ai.ask'), ['cau_hoi' => 'tôi đang thích gì'])->assertOk();
        $this->actingAs(User::factory()->create())->postJson(route('shop.ai.ask'), ['cau_hoi' => 'tôi đang thích gì'])->assertOk();

        $ghi = Http::recorded();
        $this->assertStringContainsString('- Yêu thích: Sen đá kim cương bí mật', $this->noiDungGui($ghi[0][0]));
        $this->assertStringContainsString('- Yêu thích: (chưa có)', $this->noiDungGui($ghi[1][0]), 'Không lộ yêu thích của người khác');
        $this->assertStringNotContainsString($a->email, $this->noiDungGui($ghi[0][0]), 'Không gửi email cho AI');
    }

    #[Test]
    public function tat_suy_nghi_bo_markdown_va_noi_ro_khi_bi_cat(): void
    {
        $this->batAi();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => "**Kim tiền chậu sứ** giá 250.000₫\n* Dễ chăm\n## Lưu ý"]]],
                'finishReason' => 'MAX_TOKENS',
            ]]]),
        ]);

        $traLoi = $this->postJson(route('shop.ai.ask'), ['cau_hoi' => 'kim tiền'])->assertOk()->json('tra_loi');

        $this->assertStringNotContainsString('**', $traLoi, 'Khung chat chèn textContent: dấu markdown sẽ hiện nguyên');
        $this->assertStringContainsString("Kim tiền chậu sứ giá 250.000₫\n- Dễ chăm\nLưu ý", $traLoi);
        $this->assertStringContainsString('bị cắt', $traLoi, 'Hết hạn mức token thì nói ra, không dừng giữa câu');

        Http::assertSent(fn (Request $r) => data_get($r->data(), 'generationConfig.thinkingConfig.thinkingBudget') === 0);
    }

    #[Test]
    public function dich_vu_loi_thi_bao_ro_va_khong_luu_lich_su(): void
    {
        $this->batAi();
        $this->giaTraLoi(ma: 500);

        $this->postJson(route('shop.ai.ask'), ['cau_hoi' => 'Cây nào dễ chăm?'])
            ->assertStatus(502)
            ->assertJsonStructure(['loi']);

        $this->assertNull(session(ShoppingAdvisor::SESSION_KEY));
    }

    #[Test]
    public function cau_hoi_qua_dai_bi_tu_choi_va_lich_su_hien_ra_duoc_escape(): void
    {
        $this->batAi();
        Http::fake();

        $this->postJson(route('shop.ai.ask'), ['cau_hoi' => str_repeat('a', 501)])->assertStatus(422);
        Http::assertNothingSent();

        $html = $this->withSession([ShoppingAdvisor::SESSION_KEY => [
            ['role' => 'user', 'text' => '<script>alert(1)</script>'],
            ['role' => 'assistant', 'text' => '<img src=x onerror=alert(2)>'],
        ]])->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
