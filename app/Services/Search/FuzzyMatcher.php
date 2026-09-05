<?php

namespace App\Services\Search;

/**
 * Tìm từ gần đúng nhất trong từ điển cho một từ khoá gõ sai.
 * ============================================================
 * Đây là phần "ý bạn là..." của ô tìm kiếm: khách gõ "hoaa" hay "monstera"
 * thành "montera" thì vẫn phải ra hàng.
 *
 * DÙNG KHOẢNG CÁCH LEVENSHTEIN — số thao tác (thêm / bớt / đổi một chữ)
 * để biến từ này thành từ kia. "hoaa" -> "hoa" là 1 (bớt một chữ 'a'),
 * "ha" -> "hoa" là 1 (thêm 'o'), "montera" -> "monstera" là 1 (thêm 's').
 *
 * NGƯỠNG PHẢI TĂNG THEO ĐỘ DÀI, không được cố định:
 * với từ 2 chữ thì sai 1 chữ đã là một nửa từ ("ha" cách "ba", "ma",
 * "la" đúng 1) — nhận bừa sẽ trả về hàng chẳng liên quan. Với từ 10 chữ
 * thì sai 2 chữ vẫn gần như chắc chắn là cùng một từ.
 *
 * VỚI TỪ NGẮN CÒN BẮT BUỘC TRÙNG CHỮ ĐẦU:
 * người ta hiếm khi gõ sai chữ cái đầu — đó là chữ được nghĩ tới trước
 * và gõ chậm nhất. Ràng buộc này loại đúng những "ba/ma/la" ở trên mà
 * vẫn giữ "ha" -> "hoa".
 */
class FuzzyMatcher
{
    /**
     * Số lỗi tối đa chấp nhận, theo độ dài từ khách gõ.
     *
     * Các mốc chọn theo cách gõ thực tế chứ không phải số tròn: từ 1 chữ
     * thì không sửa gì được (mọi chữ cái đều cách nhau 1), từ 5 chữ trở
     * lên mới cho sai 2 vì lúc đó hai lỗi vẫn giữ được phần lớn hình dạng
     * của từ.
     */
    private const THRESHOLDS = [
        1 => 0,
        2 => 1,
        3 => 1,
        4 => 1,
        5 => 2,
        6 => 2,
        7 => 2,
    ];

    /** Từ dài từ 8 chữ trở lên. */
    private const THRESHOLD_LONG = 3;

    /** Từ ngắn hơn mức này thì bắt buộc trùng chữ cái đầu. */
    private const SHORT_WORD_LENGTH = 5;

    public function maxDistance(string $token): int
    {
        return self::THRESHOLDS[strlen($token)] ?? self::THRESHOLD_LONG;
    }

    /**
     * Từ trong từ điển gần $token nhất, hoặc null nếu không đủ gần.
     *
     * @param  array<string, int>  $dictionary  từ => số sản phẩm chứa từ đó
     */
    public function closest(string $token, array $dictionary): ?string
    {
        $max = $this->maxDistance($token);

        if ($max === 0 || $token === '') {
            return null;
        }

        $len = strlen($token);
        $requireSameFirst = $len < self::SHORT_WORD_LENGTH;
        $first = $token[0];

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestWeight = -1;

        foreach ($dictionary as $word => $weight) {
            $word = (string) $word;

            /*
             * Ba phép loại nhanh, xếp từ rẻ tới đắt. levenshtein() là
             * O(n×m) nên gọi nó cho cả nghìn từ mỗi lần tìm là phí; ba
             * dòng dưới loại được phần lớn từ điển chỉ bằng phép so số.
             */

            // 1. Chênh lệch độ dài đã lớn hơn ngưỡng thì không cần tính:
            //    mỗi chữ thừa/thiếu tự nó đã là một thao tác.
            if (abs(strlen($word) - $len) > $max) {
                continue;
            }

            // 2. Từ ngắn: khác chữ đầu là loại.
            if ($requireSameFirst && $word[0] !== $first) {
                continue;
            }

            $distance = levenshtein($token, $word);

            if ($distance > $max) {
                continue;
            }

            /*
             * Xếp hạng: gần hơn thắng; bằng nhau thì từ xuất hiện ở nhiều
             * sản phẩm hơn thắng — khách gõ sai một từ phổ biến vẫn khả
             * dĩ hơn là gõ sai đúng một từ hiếm. Vẫn bằng nhau nữa thì
             * lấy từ nhỏ hơn theo bảng chữ cái, để kết quả không đổi giữa
             * hai lần chạy (thứ tự từ điển có thể khác nhau).
             */
            $better = $distance < $bestDistance
                || ($distance === $bestDistance && $weight > $bestWeight)
                || ($distance === $bestDistance && $weight === $bestWeight && $best !== null && $word < $best);

            if ($better) {
                $best = $word;
                $bestDistance = $distance;
                $bestWeight = $weight;
            }
        }

        return $best;
    }
}
