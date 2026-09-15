<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
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
 * (xoá tài khoản kéo theo bài, lượt thích, bình luận — cascade).
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
     * [người đăng, tên sản phẩm (null = cây tự trồng, không ảnh), lời kể, số ngày trước, đã duyệt, số lượt thích, bình luận [người, nội dung]]
     */
    private const BAI = [
        [0, 'Monstera Deliciosa chậu gốm', 'Bé Monstera về nhà được 3 tuần, vừa ra thêm một lá xẻ thuỳ mới. Mình để cách cửa sổ hướng đông khoảng 1m, tưới khi đất khô 2 đốt ngón tay.', 2, true, 4, [[2, 'Lá xẻ đẹp quá! Bạn có lau lá thường xuyên không?'], [0, 'Có, mình lau bằng khăn ẩm mỗi tuần một lần.']]],
        [1, 'Kim tiền chậu sứ', 'Góc làm việc mới với chậu kim tiền. Để trong phòng máy lạnh vẫn xanh tốt, nửa tháng mới tưới một lần.', 4, true, 3, [[3, 'Kim tiền đúng là dễ chăm thật, nhà mình cũng có một chậu.']]],
        [2, 'Sen đá mix chậu đá', 'Khay sen đá phơi nắng sáng ngoài ban công. Sau hai tuần màu lá đậm lên rõ, viền hồng hơn lúc mới mua.', 5, true, 5, [[4, 'Màu lên đẹp ghê. Mùa mưa bạn có che không?'], [2, 'Mưa to thì mình kéo vào mái hiên, sen đá sợ úng lắm.']]],
        [3, 'Lưỡi hổ mini để bàn', 'Hai chậu lưỡi hổ mini cạnh kệ sách. Phòng ít nắng nhưng cây vẫn đứng lá, không bị rũ.', 7, true, 2, []],
        [4, 'Lan hồ điệp tím chậu sứ', 'Chậu lan hồ điệp nở bền gần một tháng rồi. Mình tưới bằng cách nhúng chậu vào nước 10 phút mỗi tuần.', 9, true, 4, [[1, 'Mẹo nhúng chậu hay quá, cảm ơn bạn.']]],
        [0, 'Dương xỉ Boston treo', 'Treo dương xỉ ở hiên nhà tắm, độ ẩm cao nên lá mọc dày hẳn. Chỉ cần xịt thêm nước vào hôm hanh khô.', 12, true, 1, []],
        [1, 'Bonsai mai chiếu thuỷ', 'Tỉa lại tán mai chiếu thuỷ cho gọn trước khi ra hoa. Lần đầu tự tỉa nên hơi run tay.', 15, true, 3, [[3, 'Dáng đẹp mà. Lần sau bạn chụp lúc ra hoa nhé!']]],
        [2, null, 'Không có ảnh vì cây ở nhà bà ngoại: chậu trầu bà bà trồng từ một đoạn cành, giờ đã leo kín cột hiên. Ai định giâm cành thì cứ thử, trầu bà rất dễ ra rễ trong nước.', 18, true, 2, []],
        // Một bài đang chờ duyệt — để trang quản trị có việc để xem, và bài này KHÔNG hiện ra ngoài.
        [3, 'Xương rồng bi chậu đất nung', 'Chậu xương rồng bi mới mua, đang tìm chỗ nắng nhất trong nhà cho bé.', 0, false, 0, []],
    ];

    public function run(): void
    {
        $nguoi = collect(self::NGUOI)->map(fn (array $n) => $this->taiKhoan($n[0], $n[1]));
        $anh = app(ImageStore::class);
        $taoMoi = 0;

        foreach (self::BAI as [$ai, $tenSanPham, $loiKe, $ngayTruoc, $daDuyet, $soThich, $binhLuan]) {
            $tacGia = $nguoi[$ai];

            if (CommunityPost::query()->where('user_id', $tacGia->id)->where('body', $loiKe)->exists()) {
                continue;
            }

            $sanPham = $tenSanPham ? Product::query()->where('name', $tenSanPham)->first() : null;
            $moc = now()->subDays($ngayTruoc)->subHours(($ai + 1) * 3);

            $bai = new CommunityPost([
                'body' => $loiKe,
                'photo' => $sanPham ? $this->chepAnh($sanPham, $anh) : null,
                'product_id' => $sanPham?->id,
            ]);
            $bai->forceFill([
                'user_id' => $tacGia->id,
                'approved_at' => $daDuyet ? $moc->copy()->addHours(2) : null,
                'created_at' => $moc,
                'updated_at' => $moc,
            ])->save();

            $nguoiThich = $nguoi->reject(fn (User $u) => $u->id === $tacGia->id)->take($soThich);
            DB::table('community_post_likes')->insertOrIgnore($nguoiThich->map(fn (User $u) => [
                'community_post_id' => $bai->id,
                'user_id' => $u->id,
                'created_at' => $moc->copy()->addHours(5),
            ])->values()->all());

            foreach ($binhLuan as $i => [$aiViet, $noiDung]) {
                $bl = new CommunityComment(['body' => $noiDung]);
                $bl->forceFill([
                    'community_post_id' => $bai->id,
                    'user_id' => $nguoi[$aiViet]->id,
                    'created_at' => $moc->copy()->addHours(6 + $i * 3),
                    'updated_at' => $moc->copy()->addHours(6 + $i * 3),
                ])->save();
            }

            $taoMoi++;
        }

        $this->command?->info("Đã tạo {$taoMoi} bài Góc cây mẫu (bỏ qua bài đã có).");
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
