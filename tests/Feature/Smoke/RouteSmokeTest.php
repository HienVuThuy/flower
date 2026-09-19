<?php

namespace Tests\Feature\Smoke;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Lưới an toàn: không trang nào được vỡ thành lỗi 500. */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const THAM_SO = [
        'product' => \App\Models\Product::class,
        'category' => \App\Models\Category::class,
        'order' => \App\Models\Order::class,
        'promotion' => \App\Models\Promotion::class,
        'coupon' => \App\Models\Coupon::class,
        'user' => \App\Models\User::class,
        'review' => \App\Models\Review::class,
        'post' => \App\Models\BlogPost::class,
        'blogPost' => \App\Models\BlogPost::class,
        'journal' => \App\Models\Journal::class,
        'supplier' => \App\Models\Supplier::class,
        'flowerLot' => \App\Models\FlowerLot::class,
        'expense' => \App\Models\Expense::class,
        'giftItem' => \App\Models\GiftItem::class,
        'productGift' => \App\Models\ProductGift::class,
    ];

    private const CHUA_PHU = [
        'admin/bulk-inquiries/{bulkInquiry}',
        'admin/doi-hang/{exchange}',
        'admin/kiem-ke/{stockCount}',
        'admin/nhap-kho/{stockReceipt}',
        'admin/orders/{order}',
        'admin/orders/{order}/in',
        'dat-lai-mat-khau/{token}',
        'dia-chi/{address}/sua',
        'dia-gioi/phuong-xa/{districtId}',
        'dia-gioi/quan-huyen/{provinceId}',
        'don-hang/{order}',
        'don-hang/{order}/thanh-toan-momo',
        'don-hang/{order}/tra-gop-momo',
        'loai-cay/{taxon}',
        'nhat-ky/{journal}',
        'nhat-ky/{journal}/sua',
        'nhu-cau/{intent}',
        'storage/{path}',
        'thanh-toan/momo/{order}',
        'thong-bao/{notification}',
        'trang/{slug}',
        'xac-thuc-email/{id}/{hash}',
    ];

    private const BO_QUA = [
        'up',
        'logout',
        'dang-xuat',
    ];

    #[Test]
    public function khong_trang_nao_tra_ve_loi_500(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->duLieuChoThamSo();

        $loi = [];
        $boQua = [];
        $trangQuanTriVaoDuoc = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::BO_QUA, true)) {
                continue;
            }

            $duong = $this->thayThamSo($uri);

            if ($duong === null) {
                $boQua[] = $uri;

                continue;
            }

            $res = $this->actingAs($admin)->get($duong);

            if ($res->getStatusCode() >= 500) {
                $loi[] = '/' . $uri . ' => ' . $res->getStatusCode();
            }

            if (str_starts_with($uri, 'admin') && $res->getStatusCode() === 200) {
                $trangQuanTriVaoDuoc++;
            }
        }

        $this->assertGreaterThan(
            5,
            $trangQuanTriVaoDuoc,
            'Bài quét không mở được trang quản trị nào — nó đang không canh gác phần đó.',
        );

        $this->assertSame([], $loi, "Có trang trả về lỗi máy chủ:\n" . implode("\n", $loi));

        sort($boQua);

        $this->assertSame(
            self::CHUA_PHU,
            $boQua,
            "Danh sách đường dẫn không quét được đã đổi.\nHiện tại:\n" . implode("\n", $boQua),
        );
    }

    private function duLieuChoThamSo(): void
    {
        \App\Models\Product::factory()
            ->for(\App\Models\Category::factory())
            ->create();

        \App\Models\Coupon::factory()->create();

        \App\Models\Supplier::create([
            'name' => 'Vựa quét thử',
            'kind' => 'vua',
        ]);

        $loai = \App\Models\FlowerKind::create(['name' => 'Hoa quét thử', 'default_unit' => 'bo']);
        (new \App\Models\FlowerLot())->forceFill([
            'code' => 'LH-QUET-THU',
            'flower_kind_id' => $loai->id,
            'purchased_at' => now()->toDateString(),
            'quantity' => '10.00',
            'unit' => 'bo',
            'total_cost' => '500000.00',
            'status' => 'dang_dung',
        ])->save();

        \App\Models\Expense::create([
            'spent_on' => now()->toDateString(),
            'category' => 'luong',
            'description' => 'Khoản chi quét thử',
            'amount' => '1000000.00',
        ]);

        $qua = \App\Models\GiftItem::create(['name' => 'Túi vải quét thử', 'kind' => 'qua_tang', 'stock_quantity' => 10, 'is_active' => true]);
        \App\Models\Promotion::create(['name' => 'Quà quét thử', 'slug' => 'qua-quet-thu', 'type' => 'tang_qua', 'discount_value' => 0, 'gift_item_id' => $qua->id, 'status' => 'active', 'priority' => 0]);

        $quaKem = new \App\Models\ProductGift(['per_quantity' => 1, 'gift_quantity' => 1, 'is_active' => true]);
        $quaKem->product_id = \App\Models\Product::query()->value('id');
        $quaKem->gift_item_id = $qua->id;
        $quaKem->save();

        \App\Models\Promotion::create([
            'name' => 'Chương trình quét thử',
            'slug' => 'chuong-trinh-quet-thu',
        ]);

        $danhMuc = \App\Models\BlogCategory::create([
            'name' => 'Chuyên mục quét thử',
            'slug' => 'chuyen-muc-quet-thu',
        ]);

        \App\Models\BlogPost::create([
            'title' => 'Bài viết quét thử',
            'slug' => 'bai-viet-quet-thu',
            'body' => '<p>Nội dung.</p>',
            'blog_category_id' => $danhMuc->id,
        ]);
    }

    private function thayThamSo(string $uri): ?string
    {
        $duong = '/' . ltrim($uri, '/');

        foreach (self::THAM_SO as $ten => $model) {
            foreach (['{' . $ten . '}', '{' . $ten . '?}'] as $cho) {
                if (! str_contains($duong, $cho)) {
                    continue;
                }

                $ban = $model::query()->first();

                if ($ban === null) {
                    return null;
                }

                $duong = str_replace($cho, (string) $ban->getRouteKey(), $duong);
            }
        }

        return str_contains($duong, '{') ? null : $duong;
    }
}
