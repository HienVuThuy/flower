<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBlock;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Video sản phẩm và mô tả chi tiết theo khối. */
class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Chậu sen đá kiểm thử',
            'slug' => 'chau-sen-da-kiem-thu',
            'product_code' => 'KT-' . strtoupper(bin2hex(random_bytes(3))),
            'product_type' => ProductType::Plant->value,
            'selling_form' => SellingForm::Pot->value,
            'base_price' => '150000',
            'track_inventory' => '1',
            'stock_quantity' => '10',
            'status' => 'active',
        ], $ghiDe);
    }

    private function tao(array $ghiDe = [])
    {
        return $this->actingAs($this->admin())->post('/admin/products', $this->duLieu($ghiDe));
    }

    private function anh(): UploadedFile
    {
        return UploadedFile::fake()->image('khoi.jpg', 800, 600);
    }

    #[Test]
    public function link_youtube_duoc_dung_lai_thanh_dia_chi_nhung_khong_luu_nguyen_chuoi(): void
    {
        $this->tao(['video_urls' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=rac']])
            ->assertSessionHasNoErrors();

        $video = ProductImage::where('kind', ProductImage::VIDEO)->first();

        $this->assertNotNull($video);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $video->video_url);
        $this->assertNull($video->path, 'Video dạng link không có tệp nào trên đĩa.');
    }

    #[Test]
    public function tu_choi_link_khong_phai_youtube_hay_vimeo(): void
    {
        foreach ([
            'javascript:alert(1)',
            'https://ke-xau.test/embed/abc',
            'data:text/html,<script>alert(1)</script>',
        ] as $link) {
            $this->tao(['slug' => 'sp-' . bin2hex(random_bytes(3)), 'video_urls' => [$link]])
                ->assertSessionHasErrors('video_urls.0');
        }

        $this->assertSame(0, ProductImage::where('kind', ProductImage::VIDEO)->count());
    }

    #[Test]
    public function tai_len_duoc_tep_mp4_va_tu_choi_tep_gia_danh(): void
    {
        $this->tao(['video_files' => [UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4')]])
            ->assertSessionHasNoErrors();

        $video = ProductImage::where('kind', ProductImage::VIDEO)->first();

        $this->assertNotNull($video->path);
        Storage::disk('public')->assertExists($video->path);

        $this->tao([
            'slug' => 'sp-gia-danh',
            'video_files' => [UploadedFile::fake()->create('clip.mp4', 10, 'application/x-php')],
        ])->assertSessionHasErrors('video_files.0');
    }

    #[Test]
    public function video_khong_lot_vao_thu_vien_anh(): void
    {
        $this->tao([
            'gallery' => [$this->anh()],
            'video_urls' => ['https://vimeo.com/123456789'],
        ])->assertSessionHasNoErrors();

        $sp = Product::firstOrFail();

        $this->assertCount(1, $sp->images);
        $this->assertCount(1, $sp->videos);
        $this->assertCount(2, $sp->media);
        $this->assertNotContains(null, $sp->galleryPaths());
    }

    #[Test]
    public function xoa_duoc_video_bang_cung_o_tich_nhu_anh(): void
    {
        $this->tao(['video_urls' => ['https://vimeo.com/123456789']]);

        $sp = Product::firstOrFail();
        $video = $sp->videos->first();

        $this->actingAs($this->admin())
            ->put('/admin/products/' . $sp->id, $this->duLieu(['remove_images' => [$video->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $sp->fresh()->videos()->count());
    }

    #[Test]
    public function khoi_giu_dung_thu_tu_chu_anh_chu(): void
    {
        $this->tao([
            'blocks' => [
                ['kind' => 'text', 'body' => 'Đoạn mở đầu'],
                ['kind' => 'image', 'caption' => 'Ảnh giữa bài', 'image' => $this->anh()],
                ['kind' => 'text', 'body' => 'Đoạn kết'],
            ],
        ])->assertSessionHasNoErrors();

        $khoi = Product::firstOrFail()->blocks;

        $this->assertSame(['text', 'image', 'text'], $khoi->pluck('kind')->all());
        $this->assertSame([0, 1, 2], $khoi->pluck('sort_order')->all());
        Storage::disk('public')->assertExists($khoi[1]->image_path);
    }

    #[Test]
    public function khoi_chu_de_trong_va_khoi_anh_khong_co_anh_bi_bo(): void
    {
        $this->tao([
            'blocks' => [
                ['kind' => 'text', 'body' => '   '],
                ['kind' => 'image', 'caption' => 'Chưa chọn ảnh'],
                ['kind' => 'text', 'body' => 'Đoạn thật'],
            ],
        ])->assertSessionHasNoErrors();

        $khoi = Product::firstOrFail()->blocks;

        $this->assertCount(1, $khoi);
        $this->assertSame('Đoạn thật', strip_tags($khoi->first()->body));
    }

    #[Test]
    public function chu_trong_khoi_di_qua_bo_loc_html(): void
    {
        $this->tao([
            'blocks' => [['kind' => 'text', 'body' => '<p>An toàn</p><script>alert(1)</script><a href="javascript:alert(1)">bấm</a>']],
        ])->assertSessionHasNoErrors();

        $body = Product::firstOrFail()->blocks->first()->body;

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('An toàn', $body);
    }

    #[Test]
    public function bo_mot_khoi_khoi_bieu_mau_thi_xoa_ca_ban_ghi_lan_tep_anh(): void
    {
        $this->tao([
            'blocks' => [
                ['kind' => 'text', 'body' => 'Giữ lại'],
                ['kind' => 'image', 'image' => $this->anh()],
            ],
        ]);

        $sp = Product::firstOrFail();
        $khoiChu = $sp->blocks->firstWhere('kind', 'text');
        $anhCu = $sp->blocks->firstWhere('kind', 'image')->image_path;

        $this->actingAs($this->admin())
            ->put('/admin/products/' . $sp->id, $this->duLieu([
                'blocks' => [['kind' => 'text', 'id' => $khoiChu->id, 'body' => 'Giữ lại']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ProductBlock::count());
        Storage::disk('public')->assertMissing($anhCu);
    }

    #[Test]
    public function trang_khach_hien_khoi_dung_thu_tu_va_KHONG_nap_san_trinh_phat(): void
    {
        $this->tao([
            'blocks' => [
                ['kind' => 'text', 'body' => 'Phần trên ảnh'],
                ['kind' => 'image', 'caption' => 'Chú thích ảnh', 'image' => $this->anh()],
                ['kind' => 'text', 'body' => 'Phần dưới ảnh'],
            ],
            'video_urls' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ]);

        $html = $this->get('/san-pham/chau-sen-da-kiem-thu')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Chú thích ảnh'),
            strpos($html, 'Phần trên ảnh'),
            'Khối chữ đầu phải nằm trước ảnh.',
        );
        $this->assertLessThan(strpos($html, 'Phần dưới ảnh'), strpos($html, 'Chú thích ảnh'));

        $this->assertStringNotContainsString('<iframe', $html);

        $this->assertStringContainsString('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $html);
    }

    #[Test]
    public function o_so_luong_ton_bi_khoa_khi_tat_quan_ly_ton_kho(): void
    {
        $tat = Product::factory()->for(Category::factory())->create(['track_inventory' => false, 'stock_quantity' => 7]);
        $bat = Product::factory()->for(Category::factory())->create(['track_inventory' => true, 'stock_quantity' => 7]);

        $admin = $this->admin();

        $html = $this->actingAs($admin)->get('/admin/products/' . $tat->id . '/edit')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<input[^>]*name="stock_quantity"[^>]*readonly#s', $html);
        $this->assertStringNotContainsString('name="stock_quantity" disabled', $html);

        $html = $this->actingAs($admin)->get('/admin/products/' . $bat->id . '/edit')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#<input[^>]*name="stock_quantity"[^>]*readonly#s', $html);
    }

    #[Test]
    public function trang_xem_san_pham_o_admin_noi_duoc_no_ban_the_nao(): void
    {
        $this->tao(['video_urls' => ['https://vimeo.com/123456789']]);

        $sp = Product::firstOrFail();

        $this->actingAs($this->admin())
            ->get('/admin/products/' . $sp->id)
            ->assertOk()
            ->assertSee('Đã bán')
            ->assertSee('Doanh thu')
            ->assertSee('Đánh giá')
            ->assertSee('Thư viện')
            ->assertSee('Đường dẫn và SEO');
    }
}
