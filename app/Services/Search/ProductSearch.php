<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * Điều phối việc tìm sản phẩm: chuẩn hoá -> sửa lỗi gõ -> lọc -> xếp hạng.
 * ============================================================
 * ĐÂY LÀ CỬA DUY NHẤT để tìm sản phẩm theo từ khoá. Trang danh sách,
 * trang danh mục hay bất cứ chỗ nào sau này cần ô tìm kiếm đều gọi vào
 * đây, nên tất cả cùng một hành vi — không có chỗ nào "tìm hơi khác".
 *
 * BA LỚP KHỚP, xếp từ chặt tới lỏng, chỉ hạ xuống lớp sau khi lớp trước
 * không ra gì:
 *
 *   1. BỎ DẤU. "hoa hong" khớp "Hoa hồng đỏ Ecuador". Đây là lớp gánh
 *      nhiều việc nhất trong thực tế — phần lớn khách không bật bộ gõ
 *      tiếng Việt khi tìm hàng.
 *   2. SỬA LỖI GÕ. "hoaa", "ha", "montera" — chỉ chạy khi từ khoá KHÔNG
 *      có trong catalog, nên khách gõ đúng thì không bao giờ bị đoán sai
 *      thành thứ khác.
 *   3. NỚI LỎNG (do controller quyết định gọi hay không). "hoa bonsai"
 *      không có sản phẩm nào chứa cả hai từ; thay vì trả về trang trống,
 *      hiện sản phẩm khớp một phần và NÓI RÕ là khớp một phần.
 *
 * XẾP HẠNG tách khỏi lọc: khách chọn "Giá tăng dần" thì ý muốn đó thắng,
 * xếp theo độ liên quan chỉ áp dụng khi khách chưa chọn kiểu sắp xếp nào.
 */
class ProductSearch
{
    /**
     * Từ gợi ý thêm phải phổ biến gấp bằng này lần thì mới hỏi khách.
     * Xem giải thích ở alternativeFor().
     */
    private const ALTERNATIVE_WEIGHT_FACTOR = 2;

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly SearchDictionary $dictionary,
        private readonly FuzzyMatcher $matcher,
    ) {
    }

    /**
     * Phân tích từ khoá thô của khách.
     *
     * KHÔNG chạm cơ sở dữ liệu ngoài từ điển (đã cache), nên gọi hàm này
     * không tốn thêm truy vấn nào cho mỗi lần tìm.
     */
    public function terms(?string $raw): SearchTerms
    {
        $original = trim((string) $raw);
        $tokens = $this->normalizer->tokenize($original);

        if ($tokens === []) {
            return SearchTerms::empty($original);
        }

        $final = [];
        $corrections = [];

        foreach ($tokens as $token) {
            // Từ có thật trong catalog thì để yên. Đây là điều kiện quan
            // trọng nhất của cả cơ chế: không bao giờ "sửa" một từ khoá
            // vốn đã tìm được hàng.
            if ($this->dictionary->matches($token)) {
                $final[$token] = true;

                continue;
            }

            $fixed = $this->matcher->closest($token, $this->dictionary->words());

            if ($fixed === null) {
                // Không đoán được thì giữ nguyên chữ khách gõ. Trả về
                // trang trống với đúng từ khoá họ nhập vẫn trung thực hơn
                // là lặng lẽ bỏ từ đó đi rồi hiện hàng chẳng liên quan.
                $final[$token] = true;

                continue;
            }

            $final[$fixed] = true;
            $corrections[$token] = $fixed;
        }

        $tokens = array_keys($final);

        return new SearchTerms(
            $original,
            $tokens,
            $corrections,
            // Gợi ý thêm chỉ xét khi từ khoá đúng nguyên vẹn một chữ.
            $corrections === [] && count($tokens) === 1
                ? $this->alternativeFor($tokens[0])
                : null,
        );
    }

    /**
     * Từ khoá gần giống nhưng phổ biến hơn HẲN — gợi ý thêm, không thay thế.
     * ============================================================
     * Khác hoàn toàn với phần sửa lỗi gõ ở trên. Sửa lỗi chỉ chạy khi từ
     * khoá KHÔNG có hàng; hàm này chạy khi từ khoá CÓ hàng nhưng nhiều
     * khả năng khách muốn thứ khác.
     *
     * Trường hợp thật trong catalog này: gõ "ha" thì đúng là có
     * "Bó tulip Hà Lan" — không có gì sai để mà sửa. Nhưng "hoa" chỉ cách
     * một chữ và có mặt ở bảy sản phẩm, nên khả năng cao khách đang gõ dở
     * chữ "hoa". Việc đúng là VẪN trả kết quả cho "ha" và hỏi thêm một
     * câu, chứ không phải lặng lẽ đổi từ khoá của khách.
     *
     * NGƯỠNG GẤP ĐÔI để không hỏi bừa: nếu từ khách gõ vốn đã phổ biến
     * ngang ngửa từ kia thì họ gõ có chủ đích, hỏi lại chỉ gây nhiễu.
     */
    private function alternativeFor(string $token): ?string
    {
        $words = $this->dictionary->words();

        foreach ($words as $word => $weight) {
            /*
             * ĐANG GÕ DỞ MỘT TỪ CÓ THẬT thì im lặng.
             *
             * "mon" là phần đầu của "monstera", "bon" là phần đầu của
             * "bonsai" — khách chưa gõ xong chứ không gõ sai. Không có
             * luật này thì hệ thống chen ngang bằng những gợi ý ngớ ngẩn
             * ("mon" -> "một", "bon" -> "bó") đúng lúc khách đang trên
             * đường tới thứ họ muốn.
             *
             * Chú ý `strlen(...) > strlen($token)`: bằng nhau nghĩa là
             * khách đã gõ trọn vẹn một từ có thật, lúc đó gợi ý thêm mới
             * có ý nghĩa ("ha" -> "hoa").
             */
            if (strlen((string) $word) > strlen($token) && str_starts_with((string) $word, $token)) {
                return null;
            }
        }

        // Độ phổ biến của chính thứ khách đang tìm.
        $baseWeight = $words[$token] ?? 0;

        // Chỉ xét những từ KHÁC hẳn: từ đã bắt đầu bằng chính từ khoá thì
        // vốn đã nằm trong kết quả rồi, gợi ý lại là thừa.
        $others = array_filter(
            $words,
            fn ($word) => ! str_starts_with((string) $word, $token),
            ARRAY_FILTER_USE_KEY,
        );

        $best = $this->matcher->closest($token, $others);

        if ($best === null) {
            return null;
        }

        return $words[$best] > $baseWeight * self::ALTERNATIVE_WEIGHT_FACTOR
            ? $best
            : null;
    }

    /**
     * Thêm điều kiện lọc theo từ khoá vào câu truy vấn.
     *
     * @param  bool  $matchAll  true = sản phẩm phải chứa MỌI từ khoá (mặc
     *                          định, cho kết quả sát ý); false = chứa ít
     *                          nhất một từ (chế độ nới lỏng)
     */
    public function filter(Builder $query, SearchTerms $terms, bool $matchAll = true): void
    {
        if ($terms->isEmpty()) {
            return;
        }

        /*
         * Bọc trong một nhóm ngoặc.
         *
         * Câu truy vấn ở controller còn có các bộ lọc khác (danh mục,
         * hình thức bán, trạng thái). Ở chế độ nới lỏng, chuỗi OR mà
         * không có ngoặc sẽ nuốt luôn các điều kiện đó và trả về cả sản
         * phẩm đã ẩn — lỗi kinh điển của việc ghép OR vào WHERE có sẵn.
         */
        $query->where(function (Builder $group) use ($terms, $matchAll) {
            foreach ($terms->tokens as $index => $token) {
                $condition = fn (Builder $q) => $this->matchToken($q, 'search_text', $token);

                if ($matchAll || $index === 0) {
                    $group->where($condition);
                } else {
                    $group->orWhere($condition);
                }
            }
        });
    }

    /**
     * Từ khoá phải bắt đầu một TIẾNG, không được nằm lọt giữa tiếng khác.
     * ============================================================
     * Đây là chỗ quyết định chất lượng kết quả nhiều nhất, và bài học
     * phải trả giá mới thấy: bản đầu tiên khớp chuỗi con ở bất kỳ đâu
     * (LIKE '%hong%'), kết quả là gõ "hồng" ra cả cây Monstera — vì mô tả
     * của nó có chữ "không gian" và "phòng khách", cả hai đều chứa "hong".
     * Tiếng Việt bỏ dấu có rất nhiều tiếng lồng vào nhau kiểu đó, nên
     * khớp chuỗi con là công thức chắc chắn sinh nhiễu.
     *
     * Khớp theo đầu tiếng thì vẫn giữ được cách gõ tự nhiên: "mons" ra
     * "monstera", "tu" ra "tulip" — người ta gõ dở chừng từ ĐẦU chứ không
     * gõ khúc giữa.
     *
     * Hai vế OR là bắt buộc: LIKE 'hoa%' bắt tiếng đầu chuỗi, LIKE
     * '% hoa%' bắt các tiếng sau. Thiếu vế đầu thì gõ đúng tên sản phẩm
     * lại không ra chính nó.
     */
    private function matchToken(Builder $query, string $column, string $token): Builder
    {
        $escaped = $this->escapeLike($token);

        return $query
            ->where($column, 'like', $escaped.'%')
            ->orWhere($column, 'like', '% '.$escaped.'%');
    }

    /**
     * Sắp xếp theo độ liên quan.
     *
     * Điểm được cộng dồn từ những dấu hiệu càng chắc chắn càng nhiều
     * điểm. Trọng số chọn để một dấu hiệu mạnh luôn thắng nhiều dấu hiệu
     * yếu cộng lại — nếu không, một sản phẩm nhắc từ khoá dăm lần trong
     * mô tả sẽ vượt mặt sản phẩm mang đúng cái tên khách gõ.
     *
     * Ví dụ với từ khoá "hoa hong":
     *   "Hoa hồng đỏ Ecuador"  -> 50 (tên bắt đầu bằng cụm) + 25 + 10 + 10
     *   "Hộp hoa hồng pastel"  -> 25 (có chứa cụm)          + 10 + 10
     *   "Giỏ hoa baby trắng"   -> 10 (tên có chữ "hoa")
     */
    public function orderByRelevance(Builder $query, SearchTerms $terms): void
    {
        if ($terms->isEmpty()) {
            return;
        }

        $phrase = $terms->suggestion();

        $sql = [];
        $bindings = [];

        // Tên trùng khít cụm từ khoá — dấu hiệu mạnh nhất có thể có.
        $sql[] = 'CASE WHEN search_name = ? THEN 100 ELSE 0 END';
        $bindings[] = $phrase;

        // Tên bắt đầu bằng cụm từ khoá.
        $sql[] = 'CASE WHEN search_name LIKE ? THEN 50 ELSE 0 END';
        $bindings[] = $this->escapeLike($phrase).'%';

        // Cả cụm nằm liền nhau ở đâu đó trong phần văn bản tìm kiếm.
        $sql[] = 'CASE WHEN search_text LIKE ? THEN 25 ELSE 0 END';
        $bindings[] = '%'.$this->escapeLike($phrase).'%';

        // Từng từ khoá lẻ mở đầu một tiếng trong TÊN. Cộng dồn, nên sản
        // phẩm khớp nhiều từ hơn tự khắc đứng trên.
        foreach ($terms->tokens as $token) {
            $escaped = $this->escapeLike($token);
            $sql[] = 'CASE WHEN search_name LIKE ? OR search_name LIKE ? THEN 10 ELSE 0 END';
            $bindings[] = $escaped.'%';
            $bindings[] = '% '.$escaped.'%';
        }

        $query->orderByRaw('('.implode(' + ', $sql).') DESC', $bindings);
    }

    /**
     * Vô hiệu hoá ký tự đại diện của LIKE.
     *
     * TextNormalizer đã lọc sạch chỉ còn a-z0-9 nên hiện tại không có ký
     * tự nào cần thoát. Giữ hàm này vì nó là phòng tuyến thứ hai: hôm nào
     * bộ chuẩn hoá được nới ra (cho phép dấu gạch, chẳng hạn) thì thiếu
     * nó, một từ khoá toàn dấu '%' sẽ khớp sạch catalog và bắt cơ sở dữ
     * liệu quét toàn bảng.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
