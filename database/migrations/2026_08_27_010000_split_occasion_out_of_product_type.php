<?php

use App\Enums\CareProfile;
use App\Enums\SellingForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MAP = [
        'lang-hoa-khai-truong'         => ['flower', 'event'],
        'hoa-cam-tay-co-dau'           => ['flower', 'wedding'],
        'hop-hoa-hong-pastel'          => ['flower', 'gift'],
        'set-qua-cay-de-ban-kem-thiep' => ['plant',  'gift'],
    ];

    public function up(): void
    {
        foreach (self::MAP as $slug => [$new, $old]) {
            DB::table('products')
                ->where('slug', $slug)
                ->where('product_type', $old)
                ->update(['product_type' => $new]);
        }

        $legacy = DB::table('products')
            ->whereNotIn('product_type', ['flower', 'plant', 'other'])
            ->get(['id', 'selling_form']);

        foreach ($legacy as $row) {
            $isLivingPlant = SellingForm::tryFrom((string) $row->selling_form)
                ?->careProfile() === CareProfile::LivingPlant;

            DB::table('products')
                ->where('id', $row->id)
                ->update(['product_type' => $isLivingPlant ? 'plant' : 'flower']);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $slug => [$new, $old]) {
            DB::table('products')
                ->where('slug', $slug)
                ->where('product_type', $new)
                ->update(['product_type' => $old]);
        }
    }
};
