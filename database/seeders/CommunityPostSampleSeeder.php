<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\ImageStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Dữ liệu mẫu cho "Góc cây của bạn".
 * ============================================================
 * ⚠️ DỮ LIỆU MẪU. Người đăng là các tài khoản mẫu (@khachmau.test, cùng bộ với
 * ReviewSampleSeeder), lời kể do soạn ra. Cửa hàng thật PHẢI xoá trước khi bán:
 *
 *     php artisan tinker --execute="App\Models\User::where('email','like','%@khachmau.test')->each->delete();"
 *
 * (xoá tài khoản kéo theo bài, ảnh, lượt thích, bình luận — cascade).
 *
 * ẢNH: chép từ ảnh sản phẩm ĐÃ CÓ trong máy (storage/app/public/products — ảnh
 * có ghi nguồn trong credits.json) sang thư mục `community/`, không lấy ảnh từ
 * website khác. Chép chứ không trỏ chung đường dẫn: admin xoá bài sẽ xoá ảnh
 * của bài, không được kéo theo ảnh sản phẩm.
 *
 * KHÔNG cộng điểm thưởng: bài mẫu ghi thẳng, không đi qua CommunityReward —
 * sổ điểm chỉ ghi điểm của việc đã thật sự xảy ra.
 *
 * Chạy lại an toàn: bài trùng người đăng + nội dung thì bỏ qua.
 */
class CommunityPostSampleSeeder extends Seeder
{
    private const NGUOI = [
        ['Lê Thị Mai Anh', 'maianh@khachmau.test'],
        ['Trần Quốc Bảo', 'quocbao@khachmau.test'],
        ['Phạm Thu Hà', 'thuha@khachmau.test'],
        ['Nguyễn Minh Đức', 'minhduc@khachmau.test'],
        ['Vũ Khánh Linh', 'khanhlinh@khachmau.test'],
    ];

    /**
     * Bài mẫu. `anh_them` cho bài nhiều ảnh (để thấy lưới ảnh);
     * bình luận có `tra_loi` => trả lời bình luận ngay trước nó.
     */
    private const BAI = [
        [
            'ai' => 0, 'cay' => 'Monstera Deliciosa chậu gốm', 'anh_them' => ['Trầu bà leo cột'],
            'loi' => 'Bé Monstera về nhà được 3 tuần, vừa ra thêm một lá xẻ thuỳ mới. Mình để cách cửa sổ hướng đông khoảng 1m, tưới khi đất khô 2 đốt ngón tay.',
            'ngay' => 2, 'duyet' => true, 'thich' => 4,
            'binh_luan' => [
                ['ai' => 2, 'noi_dung' => 'Lá xẻ đẹp quá! Bạn có lau lá thường xuyên không?'],
                ['ai' => 0, 'noi_dung' => 'Có, mình lau bằng khăn ẩm mỗi tuần một lần.', 'tra_loi' => true],
                ['ai' => 4, 'noi_dung' => 'Mình mới mua một bé, hóng kinh nghiệm của cả nhà.'],
            ],
        ],
        [
            'ai' => 1, 'cay' => 'Kim tiền chậu sứ',
            'loi' => 'Góc làm việc mới với chậu kim tiền. Để trong phòng máy lạnh vẫn xanh tốt, nửa tháng mới tưới một lần.',
            'ngay' => 4, 'duyet' => true, 'thich' => 3,
            'binh_luan' => [
                ['ai' => 3, 'noi_dung' => 'Kim tiền đúng là dễ chăm thật, nhà mình cũng có một chậu.'],
                ['ai' => 1, 'noi_dung' => 'Chuẩn bạn ạ, mình hay quên tưới mà cây vẫn ổn.', 'tra_loi' => true],
            ],
        ],
        [
            'ai' => 2, 'cay' => 'Sen đá mix chậu đá', 'anh_them' => ['Sen đá nâu chậu sứ mini', 'Xương rồng bi chậu đất nung'],
            'loi' => 'Khay sen đá phơi nắng sáng ngoài ban công. Sau hai tuần màu lá đậm lên rõ, viền hồng hơn lúc mới mua.',
            'ngay' => 5, 'duyet' => true, 'thich' => 5,
            'binh_luan' => [
                ['ai' => 4, 'noi_dung' => 'Màu lên đẹp ghê. Mùa mưa bạn có che không?'],
                ['ai' => 2, 'noi_dung' => 'Mưa to thì mình kéo vào mái hiên, sen đá sợ úng lắm.', 'tra_loi' => true],
            ],
        ],
        [
            'ai' => 3, 'cay' => 'Lưỡi hổ mini để bàn',
            'loi' => 'Hai chậu lưỡi hổ mini cạnh kệ sách. Phòng ít nắng nhưng cây vẫn đứng lá, không bị rũ.',
            'ngay' => 7, 'duyet' => true, 'thich' => 2, 'binh_luan' => [],
        ],
        [
            'ai' => 4, 'cay' => 'Lan hồ điệp tím chậu sứ',
            'loi' => 'Chậu lan hồ điệp nở bền gần một tháng rồi. Mình tưới bằng cách nhúng chậu vào nước 10 phút mỗi tuần.',
            'ngay' => 9, 'duyet' => true, 'thich' => 4,
            'binh_luan' => [['ai' => 1, 'noi_dung' => 'Mẹo nhúng chậu hay quá, cảm ơn bạn.']],
        ],
        [
            'ai' => 0, 'cay' => 'Dương xỉ Boston treo',
            'loi' => 'Treo dương xỉ ở hiên nhà tắm, độ ẩm cao nên lá mọc dày hẳn. Chỉ cần xịt thêm nước vào hôm hanh khô.',
            'ngay' => 12, 'duyet' => true, 'thich' => 1, 'binh_luan' => [],
        ],
        [
            'ai' => 1, 'cay' => 'Bonsai mai chiếu thuỷ',
            'loi' => 'Tỉa lại tán mai chiếu thuỷ cho gọn trước khi ra hoa. Lần đầu tự tỉa nên hơi run tay.',
            'ngay' => 15, 'duyet' => true, 'thich' => 3,
            'binh_luan' => [['ai' => 3, 'noi_dung' => 'Dáng đẹp mà. Lần sau bạn chụp lúc ra hoa nhé!']],
        ],
        [
            'ai' => 2, 'cay' => null,
            'loi' => 'Không có ảnh vì cây ở nhà bà ngoại: chậu trầu bà bà trồng từ một đoạn cành, giờ đã leo kín cột hiên. Ai định giâm cành thì cứ thử, trầu bà rất dễ ra rễ trong nước.',
            'ngay' => 18, 'duyet' => true, 'thich' => 2, 'binh_luan' => [],
        ],
        // Một bài đang chờ duyệt — để trang quản trị có việc để xem, và bài này KHÔNG hiện ra ngoài.
        [
            'ai' => 3, 'cay' => 'Xương rồng bi chậu đất nung',
            'loi' => 'Chậu xương rồng bi mới mua, đang tìm chỗ nắng nhất trong nhà cho bé.',
            'ngay' => 0, 'duyet' => false, 'thich' => 0, 'binh_luan' => [],
        ],
    ];

    public function run(): void
    {
        $nguoi = collect(self::NGUOI)->map(fn (array $n) => $this->taiKhoan($n[0], $n[1]));
        $anh = app(ImageStore::class);
        $taoMoi = 0;

        foreach (self::BAI as $mau) {
            $tacGia = $nguoi[$mau['ai']];

            if (CommunityPost::query()->where('user_id', $tacGia->id)->where('body', $mau['loi'])->exists()) {
                continue;
            }

            $sanPham = $mau['cay'] ? Product::query()->where('name', $mau['cay'])->first() : null;
            $moc = now()->subDays($mau['ngay'])->subHours(($mau['ai'] + 1) * 3);

            $bai = new CommunityPost([
                'body' => $mau['loi'],
                'product_id' => $sanPham?->id,
            ]);
            $bai->forceFill([
                'user_id' => $tacGia->id,
                'approved_at' => $mau['duyet'] ? $moc->copy()->addHours(2) : null,
                'created_at' => $moc,
                'updated_at' => $moc,
            ])->save();

            $thuTu = 0;

            foreach (array_merge($sanPham ? [$sanPham] : [], $this->anhThem($mau['anh_them'] ?? [])) as $nguon) {
                if ($duongDan = $this->chepAnh($nguon, $anh)) {
                    (new CommunityPostMedia())->forceFill([
                        'community_post_id' => $bai->id,
                        'kind' => 'image',
                        'path' => $duongDan,
                        'sort_order' => $thuTu++,
                    ])->save();
                }
            }

            $nguoiThich = $nguoi->reject(fn (User $u) => $u->id === $tacGia->id)->take($mau['thich']);
            DB::table('community_post_likes')->insertOrIgnore($nguoiThich->map(fn (User $u) => [
                'community_post_id' => $bai->id,
                'user_id' => $u->id,
                'created_at' => $moc->copy()->addHours(5),
            ])->values()->all());

            $goc = null;

            foreach ($mau['binh_luan'] as $i => $bl) {
                $traLoi = ($bl['tra_loi'] ?? false) && $goc !== null;

                $moi = new CommunityComment(['body' => $bl['noi_dung']]);
                $moi->forceFill([
                    'community_post_id' => $bai->id,
                    'user_id' => $nguoi[$bl['ai']]->id,
                    'parent_id' => $traLoi ? $goc->id : null,
                    'created_at' => $moc->copy()->addHours(6 + $i * 3),
                    'updated_at' => $moc->copy()->addHours(6 + $i * 3),
                ])->save();

                if (! $traLoi) {
                    $goc = $moi;
                }
            }

            $taoMoi++;
        }

        $this->command?->info("Đã tạo {$taoMoi} bài Góc cây mẫu (bỏ qua bài đã có).");
    }

    /**
     * @param  list<string>  $ten
     * @return list<Product>
     */
    private function anhThem(array $ten): array
    {
        return Product::query()->whereIn('name', $ten)->get()->all();
    }

    /** Chép ảnh sản phẩm sang community/ (một lần), rồi sinh bản WebP như ảnh khách tải lên. */
    private function chepAnh(Product $sanPham, ImageStore $anh): ?string
    {
        $nguon = (string) $sanPham->main_image;
        $dia = Storage::disk('public');

        if ($nguon === '' || ! $dia->exists($nguon)) {
            return null;
        }

        $dich = 'community/mau-' . basename($nguon);

        if (! $dia->exists($dich)) {
            $dia->copy($nguon, $dich);

            try {
                $anh->toiUu($dich);
            } catch (\Throwable) {
                // Không có WebP thì trang vẫn dùng ảnh gốc.
            }
        }

        return $dich;
    }

    private function taiKhoan(string $ten, string $email): User
    {
        $u = User::firstWhere('email', $email);

        if ($u) {
            return $u;
        }

        $u = new User(['name' => $ten, 'email' => $email, 'password' => 'MatKhauMau@123']);
        $u->role = UserRole::Customer;
        $u->email_verified_at = now();
        $u->save();

        return $u;
    }
}
