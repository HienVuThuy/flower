<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Bài mẫu cho Cẩm nang.
 * ============================================================
 * NỘI DUNG VIẾT THẬT, KHÔNG PHẢI "Lorem ipsum".
 *
 * Ba bài dưới đây là hướng dẫn chăm cây đúng nghiệp vụ, viết theo đúng
 * loại câu hỏi khách hỏi trước khi mua. Lý do không dùng chữ giả:
 *
 *   - trang Cẩm nang trống hoặc đầy chữ giả thì không đánh giá được bố
 *     cục có đọc nổi không — mà đó là cả điểm của việc dựng nó;
 *   - bài mẫu là chỗ admin nhìn vào để biết một bài "đúng chuẩn" trông
 *     thế nào: có tiêu đề phụ, có danh sách, có gắn sản phẩm.
 *
 * ============================================================
 * NỐI VỚI SẢN PHẨM CÓ THẬT TRONG CƠ SỞ DỮ LIỆU.
 *
 * Tìm theo slug và BỎ QUA nếu không thấy, thay vì tạo sản phẩm mới. Một
 * seeder tự tạo sản phẩm để bài của mình có cái mà trỏ tới là seeder
 * đang bịa dữ liệu bán hàng.
 */
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $chuyenMuc = $this->chuyenMuc();
        $tacGia = User::where('email', 'admin@flowerplant.test')->first()
            ?? User::orderBy('id')->first();

        foreach ($this->bai() as $i => $bai) {
            // Chạy lại seeder không được sinh bài trùng.
            if (BlogPost::withTrashed()->where('slug', $bai['slug'])->exists()) {
                continue;
            }

            $post = new BlogPost([
                'blog_category_id' => $chuyenMuc[$bai['chuyen_muc']]->id,
                'title' => $bai['title'],
                'slug' => $bai['slug'],
                'excerpt' => $bai['excerpt'],
                // Đi qua đúng lớp làm sạch mà trang quản trị dùng — nếu
                // không thì bài mẫu có thể chứa thẻ mà bài thật không
                // được phép có, và admin sẽ tưởng mình cũng dùng được.
                'body' => app(HtmlSanitizer::class)->lamSach($bai['body']),
                'published_at' => now()->subDays((count($this->bai()) - $i) * 6),
            ]);

            $post->author_id = $tacGia?->id;
            $post->save();

            $this->ganSanPham($post, $bai['san_pham'] ?? []);
        }
    }

    /** @return array<string, BlogCategory> */
    private function chuyenMuc(): array
    {
        $danhSach = [
            'cham-cay' => ['Chăm cây', 'Tưới, ánh sáng, đất, sâu bệnh — những thứ quyết định cây sống hay chết.', 1],
            'chon-cay' => ['Chọn cây', 'Cây nào hợp không gian nào, hợp người bận rộn hay người có thời gian.', 2],
            'y-nghia-hoa' => ['Ý nghĩa hoa', 'Tặng dịp nào, hoa gì, và vì sao.', 3],
        ];

        $ket = [];

        foreach ($danhSach as $slug => [$ten, $mota, $thuTu]) {
            $ket[$slug] = BlogCategory::firstOrCreate(
                ['slug' => $slug],
                ['name' => $ten, 'description' => $mota, 'sort_order' => $thuTu],
            );
        }

        return $ket;
    }

    /** @param array<string, string> $canhBao slug sản phẩm => ghi chú */
    private function ganSanPham(BlogPost $post, array $canhBao): void
    {
        $rows = [];
        $thuTu = 0;

        foreach ($canhBao as $slug => $ghiChu) {
            $sp = Product::where('slug', $slug)->first();

            // Không có thì bỏ qua — KHÔNG tạo sản phẩm mới cho vừa bài.
            if ($sp) {
                $rows[$sp->id] = ['note' => $ghiChu, 'sort_order' => $thuTu++];
            }
        }

        if ($rows !== []) {
            $post->products()->sync($rows);
        }
    }

    /** @return list<array<string, mixed>> */
    private function bai(): array
    {
        return [
            [
                'chuyen_muc' => 'chon-cay',
                'title' => 'Cây nào hợp bàn làm việc thiếu sáng?',
                'slug' => 'cay-nao-hop-ban-lam-viec-thieu-sang',
                'excerpt' => 'Bàn làm việc trong phòng kín, xa cửa sổ, chỉ có đèn huỳnh quang — '
                    .'vẫn có vài loại cây sống tốt. Đây là những cây đã được khách ở đây trồng thật.',
                'body' => <<<'HTML'
<p>Câu hỏi này hay được hỏi nhất ở cửa hàng, và câu trả lời ngắn là: <strong>có, nhưng ít loại hơn bạn nghĩ</strong>. Phần lớn cây bán làm cảnh cần ánh sáng gián tiếp — tức là sáng đủ để đọc sách thoải mái mà không cần bật đèn.</p>

<h2>Trước hết, đo thử ánh sáng bàn bạn</h2>

<p>Không cần máy đo. Giữa trưa, tắt hết đèn, đặt bàn tay cách mặt bàn khoảng 30cm:</p>

<ul>
<li><strong>Có bóng rõ nét</strong> — sáng tốt, trồng được gần như mọi cây để bàn.</li>
<li><strong>Bóng mờ, thấy hình bàn tay</strong> — sáng vừa, chọn cây chịu bóng.</li>
<li><strong>Gần như không thấy bóng</strong> — quá tối. Cây nào cũng sẽ yếu dần; nên cân nhắc đưa cây ra chỗ sáng vài ngày mỗi tuần.</li>
</ul>

<h2>Ba cây chịu bóng tốt nhất</h2>

<h3>1. Lưỡi hổ</h3>

<p>Chịu bóng giỏi nhất trong nhóm cây để bàn, và chịu được cả việc bị quên tưới. Lá dày trữ nước nên hai tuần không tưới vẫn không sao. Đây là cây nên chọn nếu bạn hay đi công tác.</p>

<blockquote>Với lưỡi hổ, tưới ít luôn an toàn hơn tưới nhiều. Thối gốc vì úng là nguyên nhân chết phổ biến nhất của loài này.</blockquote>

<h3>2. Trầu bà</h3>

<p>Leo hoặc rủ đều đẹp, và nó <em>báo</em> cho bạn biết khi thiếu nước — lá hơi mềm xuống rồi cứng lại sau khi tưới. Với người mới trồng, việc cây tự báo là một lợi thế lớn.</p>

<h3>3. Kim tiền</h3>

<p>Thân củ trữ nước, lá bóng, gần như không cần chăm. Đổi lại là nó lớn chậm — mua cây nhỏ thì một năm sau vẫn gần như thế.</p>

<h2>Cây KHÔNG nên đặt ở bàn thiếu sáng</h2>

<p>Sen đá và xương rồng nghe có vẻ dễ tính nhưng cần <strong>nắng trực tiếp</strong>. Đặt trong phòng kín thì chúng vươn dài, nhạt màu và đổ — quá trình này mất vài tháng và không đảo ngược được.</p>

<h2>Một lưu ý về chậu</h2>

<p>Chậu càng ít sáng thì đất càng lâu khô. Nếu bàn bạn tối, chọn chậu <strong>có lỗ thoát nước</strong> và đế lót, đừng dùng chậu kín — nước đọng dưới đáy là đường ngắn nhất tới thối rễ.</p>
HTML,
                'san_pham' => [
                    'luoi-ho-mini-de-ban' => 'Chịu bóng tốt nhất trong danh sách, và chịu được cả việc bị quên tưới.',
                    'trau-ba-leo-cot' => 'Tự báo khi thiếu nước — hợp người mới trồng.',
                    'kim-tien-chau-su' => 'Gần như không cần chăm, đổi lại là lớn chậm.',
                ],
            ],

            [
                'chuyen_muc' => 'cham-cay',
                'title' => 'Bao lâu tưới cây một lần? Câu trả lời không phải một con số',
                'slug' => 'bao-lau-tuoi-cay-mot-lan',
                'excerpt' => 'Mọi hướng dẫn đều nói "2 lần một tuần", và đó là lý do nhiều cây chết. '
                    .'Chu kỳ tưới phụ thuộc mùa, chậu, chỗ đặt — không phải lịch.',
                'body' => <<<'HTML'
<p>Nếu bạn tìm một con số để đặt lịch nhắc trên điện thoại, bài này sẽ làm bạn thất vọng: <strong>không có con số nào đúng cho mọi lúc</strong>. Cùng một cây, đặt cạnh cửa sổ mùa hè và đặt trong phòng máy lạnh mùa đông, chênh nhau tới ba lần.</p>

<h2>Cách đúng: nhìn đất, không nhìn lịch</h2>

<p>Cắm ngón tay xuống đất khoảng <strong>2–3cm</strong>. Khô thì tưới, còn ẩm thì đợi. Chỉ vậy thôi, và nó đúng với gần như mọi cây trồng chậu.</p>

<p>Nếu ngại bẩn tay, nhấc chậu lên: chậu khô nhẹ hơn hẳn chậu vừa tưới. Sau vài lần bạn sẽ ước lượng được bằng tay.</p>

<h2>Những gì làm đất khô nhanh hơn</h2>

<ul>
<li>Chậu đất nung (thở được) khô nhanh hơn chậu sứ hoặc nhựa.</li>
<li>Chậu nhỏ khô nhanh hơn chậu to.</li>
<li>Mùa hè, gió, và <strong>máy lạnh</strong> đều làm khô nhanh.</li>
<li>Cây đang ra lá mới uống nhiều hơn cây đang nghỉ.</li>
</ul>

<h2>Tưới bao nhiêu là đủ</h2>

<p>Tưới <strong>đến khi nước chảy ra lỗ thoát</strong>, rồi đổ bỏ phần đọng ở đế lót sau 15 phút. Tưới ít một mỗi ngày là cách làm rễ chỉ mọc ở lớp đất mặt và cây yếu hẳn.</p>

<blockquote>Thà để cây khát một hôm còn hơn để rễ ngâm nước một hôm. Cây thiếu nước thì rũ rồi hồi; cây úng thì thối rễ và không cứu được.</blockquote>

<h2>Dấu hiệu tưới sai</h2>

<table>
<thead><tr><th>Bạn thấy</th><th>Nhiều khả năng là</th></tr></thead>
<tbody>
<tr><td>Lá vàng đều, đất luôn ẩm</td><td>Tưới quá nhiều</td></tr>
<tr><td>Lá rũ, mép lá khô giòn</td><td>Tưới quá ít</td></tr>
<tr><td>Đất có mùi chua, gốc mềm</td><td>Đã thối rễ — cần thay đất ngay</td></tr>
</tbody>
</table>

<h2>Nước máy có sao không?</h2>

<p>Phần lớn cây không sao. Nhưng nếu lá cây bạn hay khô viền nâu, thử <strong>hứng nước để qua đêm</strong> cho bay clo rồi mới tưới — với một số loài như lan ý, khác biệt thấy được sau vài tuần.</p>
HTML,
                'san_pham' => [
                    'binh-tuoi-voi-dai-15l' => 'Vòi dài để tưới sát gốc, không làm ướt lá.',
                    'dia-lot-chau-chong-tran' => 'Hứng phần nước thừa — nhớ đổ bỏ sau 15 phút.',
                ],
            ],

            [
                'chuyen_muc' => 'y-nghia-hoa',
                'title' => 'Tặng hoa dịp nào thì chọn loại gì?',
                'slug' => 'tang-hoa-dip-nao-chon-loai-gi',
                'excerpt' => 'Hoa hồng không phải lúc nào cũng đúng, và hoa cúc không phải lúc nào cũng sai. '
                    .'Ghi chép từ những gì khách ở đây thật sự đặt theo từng dịp.',
                'body' => <<<'HTML'
<p>Chọn hoa sai dịp là chuyện dễ xảy ra hơn người ta tưởng, và người nhận thường không nói ra. Dưới đây là những gì khách ở cửa hàng thật sự đặt, xếp theo dịp.</p>

<h2>Sinh nhật</h2>

<p>Dịp thoải mái nhất — gần như hoa nào cũng hợp. Nếu không chắc về sở thích người nhận, <strong>hoa nhiều màu</strong> an toàn hơn một màu duy nhất: nó đọc ra là "mừng bạn" chứ không mang thông điệp riêng nào.</p>

<h2>Tỏ tình và kỷ niệm</h2>

<p>Hoa hồng đỏ vẫn là lựa chọn rõ nghĩa nhất, và sự rõ nghĩa chính là điều bạn cần ở dịp này. Hồng phấn nhẹ nhàng hơn, hợp khi mối quan hệ còn mới.</p>

<h2>Chúc mừng khai trương</h2>

<p>Ở đây quy ước khá chặt: <strong>lẵng hoa hoặc kệ hoa đứng</strong>, màu tươi, có thiệp ghi rõ tên người gửi. Bó hoa cầm tay tuy đẹp nhưng đặt ở cửa hàng mới khai trương thì không có chỗ để, và người nhận sẽ lúng túng.</p>

<h2>Thăm người ốm</h2>

<ul>
<li>Chọn hoa <strong>ít mùi</strong> — phòng bệnh kín, hương mạnh gây khó chịu.</li>
<li>Tránh hoa trắng toàn phần ở nhiều vùng, vì liên tưởng tới tang lễ.</li>
<li>Một chậu cây nhỏ đôi khi hợp hơn hoa cắt: nó sống lâu và không cần thay nước.</li>
</ul>

<h2>Ngày của mẹ, 8/3, 20/10</h2>

<p>Hoa nhẹ màu và <em>bền</em> được chuộng hơn hoa rực rỡ mà tàn sau hai ngày. Cẩm tú cầu, cúc hoạ mi và tulip đều giữ được lâu nếu thay nước hằng ngày.</p>

<h2>20/11 — Ngày Nhà giáo</h2>

<p>Khác với các dịp trên: người nhận thường nhận rất nhiều hoa cùng lúc và không mang hết về được. <strong>Chậu cây nhỏ để bàn</strong> đang được chọn nhiều hơn hẳn bó hoa, vì nó ở lại trên bàn làm việc hàng tháng.</p>

<blockquote>Một quy tắc chung: hoa cắt hợp với dịp có khoảnh khắc; chậu cây hợp với dịp muốn để lại thứ gì đó lâu dài.</blockquote>
HTML,
                'san_pham' => [
                    'hoa-hong-do-ecuador' => 'Rõ nghĩa nhất cho dịp tỏ tình và kỷ niệm.',
                    'bo-cam-tu-cau-xanh' => 'Bền, nhẹ màu — hợp 8/3 và 20/10.',
                    'set-qua-cay-de-ban-kem-thiep' => 'Ở lại trên bàn làm việc hàng tháng, hợp 20/11.',
                ],
            ],
        ];
    }
}
