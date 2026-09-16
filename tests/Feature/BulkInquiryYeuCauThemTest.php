<?php

namespace Tests\Feature;

use App\Models\BulkOrderInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Biểu mẫu số lượng lớn: yêu cầu thêm chỉ mở khi khách tick, và máy chủ bỏ giá trị của yêu cầu không tick. */
class BulkInquiryYeuCauThemTest extends TestCase
{
    use RefreshDatabase;

    private function lienHe(): array
    {
        return ['contact_name' => 'Nguyễn Văn Kiểm Thử', 'contact_phone' => '0912345678'];
    }

    #[Test]
    public function trang_co_o_tick_cho_tung_yeu_cau_them(): void
    {
        $html = $this->get(route('shop.bulk-inquiry.create'))->assertOk()->getContent();

        foreach (['ngay', 'dia_diem', 'so_luong', 'ngan_sach', 'mau', 'loai_hoa'] as $ma) {
            $this->assertStringContainsString('name="them[]" value="' . $ma . '"', $html);
        }

        $this->assertStringContainsString('data-yeu-cau-them', $html);
        $this->assertDoesNotMatchRegularExpression('#value="mau"\s+checked#', $html, 'Mặc định không tick sẵn');
    }

    #[Test]
    public function chi_luu_yeu_cau_da_tick(): void
    {
        $this->post(route('shop.bulk-inquiry.store'), $this->lienHe() + [
            'them_form' => '1',
            'them' => ['mau', 'so_luong'],
            'color_preference' => 'trắng – xanh pastel',
            'quantity_estimate' => 40,
            'flower_preference' => 'hồng Ecuador',
            'event_date' => now()->addDays(10)->toDateString(),
            'budget_min' => 5000000,
        ])->assertRedirect();

        $phieu = BulkOrderInquiry::latest('id')->firstOrFail();
        $this->assertSame('trắng – xanh pastel', $phieu->color_preference);
        $this->assertSame(40, (int) $phieu->quantity_estimate);
        $this->assertNull($phieu->flower_preference);
        $this->assertNull($phieu->event_date);
        $this->assertNull($phieu->budget_min);
    }

    #[Test]
    public function khong_tick_thi_khong_kiem_o_an_va_loi_quay_lai_giu_tick(): void
    {
        $this->post(route('shop.bulk-inquiry.store'), $this->lienHe() + [
            'them_form' => '1',
            'event_date' => now()->subDay()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->from(route('shop.bulk-inquiry.create'))->post(route('shop.bulk-inquiry.store'), [
            'them_form' => '1',
            'them' => ['mau'],
            'color_preference' => 'đỏ',
        ])->assertSessionHasErrors('contact_name');

        $this->assertMatchesRegularExpression(
            '#value="mau"\s+checked#',
            $this->get(route('shop.bulk-inquiry.create'))->getContent(),
            'Lỗi quay lại vẫn giữ yêu cầu đã tick',
        );
    }
}
