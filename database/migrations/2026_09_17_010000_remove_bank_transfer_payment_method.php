<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Gỡ hình thức "Chuyển khoản ngân hàng" khỏi hệ thống. */
return new class extends Migration
{
    private const GHI_CHU = '[Hệ thống] Đơn này vốn đặt theo hình thức "Chuyển khoản ngân hàng". '
        . 'Hình thức đó đã được gỡ khỏi hệ thống, đơn được chuyển sang COD.';

    public function up(): void
    {
        $this->doiDonSangCod();
        $this->goKhoiDieuKienCuaMaGiamGia();
        $this->xoaThongTinTaiKhoanNganHang();
    }

    private function doiDonSangCod(): void
    {
        $donCu = DB::table('orders')
            ->where('payment_method', 'bank_transfer')
            ->get(['id', 'admin_note']);

        foreach ($donCu as $don) {
            DB::table('orders')
                ->where('id', $don->id)
                ->update([
                    'payment_method' => 'cod',

                    'admin_note' => trim(($don->admin_note ? $don->admin_note . "\n\n" : '') . self::GHI_CHU),
                ]);
        }
    }

    private function goKhoiDieuKienCuaMaGiamGia(): void
    {
        $ma = DB::table('coupons')
            ->whereNotNull('payment_methods')
            ->get(['id', 'payment_methods']);

        foreach ($ma as $m) {
            $danhSach = json_decode((string) $m->payment_methods, true);

            if (! is_array($danhSach) || ! in_array('bank_transfer', $danhSach, true)) {
                continue;
            }

            $conLai = array_values(array_filter($danhSach, fn ($v) => $v !== 'bank_transfer'));

            DB::table('coupons')
                ->where('id', $m->id)
                ->update(['payment_methods' => $conLai === [] ? null : json_encode($conLai)]);
        }
    }

    private function xoaThongTinTaiKhoanNganHang(): void
    {
        DB::table('settings')
            ->whereIn('key', ['bank_name', 'bank_account_number', 'bank_account_name'])
            ->delete();
    }

    public function down(): void
    {
    }
};
