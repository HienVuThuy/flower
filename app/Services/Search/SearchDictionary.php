<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Từ điển các từ THẬT SỰ có trong catalog.
 * ============================================================
 * Hai việc, và cả hai đều cần đúng cái danh sách này:
 *
 *   1. BIẾT TRƯỚC từ khoá có ra kết quả không, mà không phải chạy thử
 *      câu truy vấn rồi đếm. Nhờ vậy quyết định "có cần sửa lỗi gõ
 *      không" chỉ tốn phép so chuỗi trong bộ nhớ, không tốn thêm lượt
 *      hỏi cơ sở dữ liệu.
 *   2. Làm nguồn gợi ý cho FuzzyMatcher. Sửa "hoaa" thành "hoa" chỉ có
 *      nghĩa khi "hoa" là chữ cửa hàng này thật sự dùng — sửa theo từ
 *      điển tiếng Việt tổng quát sẽ gợi ra những từ không có hàng.
 *
 * KHÔNG lấy từ sản phẩm ẩn / đã xoá: gợi ý ra một từ rồi bấm vào lại
 * trống trơn thì tệ hơn là không gợi ý.
 */
class SearchDictionary
{
    /**
     * Cache 1 giờ. Con số này gần như không quan trọng vì khoá cache đã
     * gắn số phiên bản — sửa sản phẩm là bản cũ bị bỏ ngay lập tức.
     * Thời hạn ở đây chỉ để dọn rác cho những bản đã hết đời.
     */
    private const TTL_SECONDS = 3600;

    /** Từ ngắn hơn mức này bị loại khỏi từ điển (xem giải thích dưới). */
    private const MIN_WORD_LENGTH = 2;

    /** @var array<string, int>|null Nhớ trong phạm vi một request. */
    private ?array $memo = null;

    /**
     * Danh sách từ => số sản phẩm chứa từ đó.
     *
     * @return array<string, int>
     */
    public function words(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $version = Cache::get(ProductSearchIndexer::VERSION_KEY, 1);

        return $this->memo = Cache::remember(
            "search.dictionary.v{$version}",
            self::TTL_SECONDS,
            fn () => $this->build(),
        );
    }

    /**
     * Từ khoá này có khớp sản phẩm nào không.
     *
     * PHẢI dùng đúng luật khớp mà câu SQL dùng — ở đây là "mở đầu một
     * tiếng" (xem ProductSearch::matchToken). Nếu hai bên định nghĩa
     * "khớp" khác nhau thì sinh ra đúng hai loại lỗi khó chịu: hoặc từ
     * khoá vốn có hàng lại bị đem đi "sửa" thành từ khác, hoặc từ khoá
     * không có hàng lại được cho qua và trả về trang trống dù bộ sửa lỗi
     * thừa sức đoán ra.
     */
    public function matches(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        foreach ($this->words() as $word => $_) {
            if (str_starts_with((string) $word, $token)) {
                return true;
            }
        }

        return false;
    }

    /** Xoá bộ nhớ tạm trong request (dùng khi kiểm thử). */
    public function flush(): void
    {
        $this->memo = null;
    }

    /**
     * @return array<string, int>
     */
    private function build(): array
    {
        $counts = [];

        Product::query()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->whereNotNull('search_text')
            ->select(['id', 'search_text'])
            ->chunkById(500, function ($products) use (&$counts) {
                foreach ($products as $product) {
                    // array_flip khử trùng trong phạm vi MỘT sản phẩm:
                    // giá trị đếm phải là "bao nhiêu sản phẩm chứa từ này",
                    // không phải "từ này xuất hiện bao nhiêu lần". Một sản
                    // phẩm nhắc "hoa" bốn lần không làm nó phổ biến hơn.
                    foreach (array_flip(explode(' ', $product->search_text)) as $word => $_) {
                        $word = (string) $word;

                        /*
                         * Bỏ từ 1 ký tự.
                         *
                         * Tiếng Việt bỏ dấu sinh ra rất nhiều mảnh 1 chữ
                         * ("ở" -> "o", "và" -> "va" thì không sao nhưng
                         * "ê", "ố" thì thành 1 chữ). Chúng khớp với gần
                         * như mọi thứ và làm hỏng phần gợi ý sửa lỗi:
                         * khoảng cách từ bất kỳ từ 2 chữ nào tới chúng
                         * cũng chỉ là 1.
                         */
                        if (strlen($word) >= self::MIN_WORD_LENGTH) {
                            $counts[$word] = ($counts[$word] ?? 0) + 1;
                        }
                    }
                }
            });

        // Sắp theo độ phổ biến giảm dần: FuzzyMatcher duyệt tuần tự và ưu
        // tiên từ phổ biến khi hoà điểm, nên gặp trước là gọn hơn.
        arsort($counts);

        return $counts;
    }
}
