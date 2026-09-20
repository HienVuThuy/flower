<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Lệnh sao lưu cơ sở dữ liệu. */
class SaoLuuTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bo_qua_khi_khong_phai_mysql_thay_vi_bao_loi(): void
    {
        $this->artisan('sao-luu:csdl')
            ->expectsOutputToContain('Chỉ hỗ trợ MySQL/MariaDB')
            ->assertSuccessful();
    }

    #[Test]
    public function co_trong_lich_chay_hang_ngay(): void
    {
        $lich = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => $e->command . ' @ ' . $e->expression);

        $this->assertTrue(
            $lich->contains(fn ($d) => str_contains($d, 'sao-luu:csdl') && str_contains($d, '30 2 * * *')),
            'Phải có lịch sao lưu hằng ngày lúc 02:30: ' . $lich->implode(' | '),
        );
    }
}
