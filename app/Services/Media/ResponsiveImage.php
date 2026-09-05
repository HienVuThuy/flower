<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Ảnh nhiều kích cỡ: chọn đúng bản cho đúng chỗ hiển thị.
 * ============================================================
 * VẤN ĐỀ ĐO ĐƯỢC: thư viện ảnh có 52 tệp JPG, tổng 5,59 MB, bề ngang
 * trung bình 882px và lớn nhất 1024px. Nhưng thẻ sản phẩm chỉ hiển thị
 * chúng ở khoảng 117–400px.
 *
 * Đo trên trang chủ: một ảnh 900×1125 được vẽ ra ở 117×146 — tức là
 * trình duyệt tải về gấp khoảng 58 lần số điểm ảnh nó cần, rồi thu nhỏ
 * lại. Trên máy tính nối mạng LAN thì không ai thấy; trên điện thoại
 * dùng 4G thì đó là toàn bộ thời gian chờ.
 *
 * ĐỌC KÍCH THƯỚC TỪ MANIFEST, KHÔNG ĐỌC TỪ TỆP ẢNH.
 *
 * Cách hiển nhiên là gọi `getimagesize()` lúc dựng trang để biết
 * width/height. Nhưng một trang danh sách có 12 thẻ sản phẩm, nên đó là
 * 12 lần mở tệp trên đĩa cho MỖI lượt xem — thay một vấn đề hiệu năng
 * bằng một vấn đề hiệu năng khác.
 *
 * Lệnh `anh:toi-uu` tính sẵn mọi kích thước vào một tệp manifest; ở đây
 * chỉ đọc manifest, và đọc một lần rồi giữ trong bộ nhớ đệm.
 */
class ResponsiveImage
{
    /**
     * Các bề ngang sinh sẵn.
     *
     * Hai mức là đủ cho cửa hàng này: 400px phủ mọi thẻ sản phẩm và ảnh
     * trong giỏ; 800px phủ ảnh lớn ở trang chi tiết và màn hình Retina
     * của thẻ. Thêm mức thứ ba là thêm 52 tệp nữa để đổi lấy vài KB.
     */
    public const WIDTHS = [400, 800];

    /** Thư mục chứa bản đã tối ưu, nằm trong đĩa `public`. */
    public const FOLDER = 'rp';

    public const MANIFEST = 'rp/manifest.json';

    /**
     * Thông tin một ảnh: kích thước gốc và các bản đã sinh.
     *
     * @return array{width:int, height:int, webp:array<int,string>}|null
     */
    public function info(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        return $this->manifest()[$path] ?? null;
    }

    /**
     * Chuỗi srcset cho các bản WebP, hoặc null nếu chưa sinh bản nào.
     *
     * Trả về null chứ không trả chuỗi rỗng: nơi gọi cần phân biệt "chưa
     * có bản tối ưu" (thì dùng thẳng ảnh gốc) với "có nhưng rỗng".
     */
    public function webpSrcset(?string $path): ?string
    {
        $info = $this->info($path);

        if (! $info || empty($info['webp'])) {
            return null;
        }

        $phan = [];

        foreach ($info['webp'] as $w => $tep) {
            $phan[] = Storage::url($tep).' '.$w.'w';
        }

        return implode(', ', $phan);
    }

    /**
     * Manifest, đọc một lần rồi giữ trong bộ nhớ đệm.
     *
     * ĐỆM VĨNH VIỄN, XOÁ BẰNG TAY khi chạy lại lệnh sinh ảnh. Đặt hạn
     * theo thời gian thì hoặc quá ngắn (đọc tệp lại liên tục) hoặc quá
     * dài (chạy lệnh xong mà trang vẫn dùng manifest cũ). Lệnh
     * `anh:toi-uu` tự xoá đệm sau khi ghi xong — đó là thời điểm DUY
     * NHẤT manifest thay đổi.
     *
     * @return array<string, array{width:int, height:int, webp:array<int,string>}>
     */
    private function manifest(): array
    {
        /*
         * NHỚ TRONG BỘ NHỚ CỦA CHÍNH REQUEST NÀY, trước khi hỏi Cache.
         *
         * LỖI ĐO ĐƯỢC TRƯỚC KHI SỬA: dự án đặt CACHE_STORE=database, nên
         * mỗi lần `Cache::rememberForever` chạy là MỘT CÂU TRUY VẤN thật
         * vào bảng `cache`. Hàm này được gọi một lần cho MỖI ẢNH, và
         * một trang danh sách có mười hai thẻ sản phẩm.
         *
         * Đếm được trên /san-pham: 25 câu `select * from cache where key
         * in (?)` trong một lần mở trang — chiếm 25 trong tổng 36 truy
         * vấn. Tức là phần tối ưu ảnh tự nó tạo ra một N+1.
         *
         * Trớ trêu ở chỗ: cái đệm sinh ra để TRÁNH đọc tệp lại, nhưng
         * lại đổi một lần đọc tệp lấy hai mươi lăm lần đi vòng qua cơ sở
         * dữ liệu.
         *
         * Manifest KHÔNG đổi giữa chừng một request, nên đọc một lần rồi
         * giữ trong thuộc tính là đủ. Cache vẫn giữ nguyên vai trò của
         * nó — tránh đọc tệp ở request SAU.
         */
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        return $this->manifest = Cache::rememberForever('anh.manifest', function () {
            $disk = Storage::disk('public');

            if (! $disk->exists(self::MANIFEST)) {
                return [];
            }

            $json = json_decode((string) $disk->get(self::MANIFEST), true);

            return is_array($json) ? $json : [];
        });
    }

    /**
     * Bản manifest đã đọc trong request này.
     *
     * null = chưa đọc lần nào. Phân biệt được với mảng rỗng (đã đọc,
     * nhưng chưa sinh ảnh) — không phân biệt thì trang chưa có ảnh nào
     * sẽ hỏi lại Cache ở mỗi thẻ, đúng cái N+1 vừa sửa.
     *
     * @var array<string, array{width:int, height:int, webp:array<int,string>}>|null
     */
    private ?array $manifest = null;

    /**
     * Gọi sau khi sinh ảnh xong, để trang đọc được manifest mới.
     *
     * Chỉ xoá đệm dùng chung. Bản nhớ trong bộ nhớ chết theo tiến trình,
     * mà lệnh sinh ảnh chạy ở một tiến trình khác với máy chủ web — nên
     * không có gì phải dọn thêm.
     */
    public static function quenManifest(): void
    {
        Cache::forget('anh.manifest');
    }
}
