<?php

namespace App\Services\Search;

/**
 * Đưa chữ tiếng Việt về một dạng chuẩn duy nhất để so khớp.
 * ============================================================
 * VÌ SAO CẦN:
 * Khách gõ "hoa hong" chứ ít khi gõ "hoa hồng" — bật bộ gõ tiếng Việt
 * chỉ để tìm một món hàng là phiền. Nếu so khớp thẳng chuỗi gốc thì
 * "hoa hong" không ra kết quả nào, dù cửa hàng có đúng thứ khách cần.
 *
 * MỘT CHỖ DUY NHẤT:
 * Cả lúc dựng chỉ mục (ProductSearchIndexer) lẫn lúc tìm (ProductSearch)
 * đều gọi cùng hàm này. Nếu hai bên chuẩn hoá khác nhau, chỉ mục và câu
 * truy vấn sẽ nói hai thứ tiếng và không bao giờ khớp — đó là loại lỗi
 * rất khó nhìn ra vì mã ở cả hai bên đều "trông đúng".
 *
 * KHÔNG dùng iconv('ASCII//TRANSLIT'): kết quả phụ thuộc locale của máy
 * chủ, trên Windows còn trả về dấu '?' cho ký tự tiếng Việt. Bảng tra
 * dưới đây là toàn bộ nguyên âm có dấu của tiếng Việt, viết rõ ra nên
 * kiểm chứng được và chạy đâu cũng như nhau.
 */
class TextNormalizer
{
    /** Dài hơn mức này thì cắt — không có từ khoá thật nào dài vậy. */
    public const MAX_QUERY_LENGTH = 100;

    /** Số từ tối đa xét trong một lần tìm. */
    public const MAX_TOKENS = 8;

    /** Từ dài hơn mức này bị cắt: levenshtein() của PHP chặn ở 255 byte. */
    public const MAX_TOKEN_LENGTH = 32;

    /**
     * Nguyên âm có dấu tiếng Việt (đã ở dạng thường) => chữ cái không dấu.
     *
     * Đủ 6 nguyên âm × 5 thanh, cộng các biến thể mũ/móc (â ă ê ô ơ ư),
     * và 'đ'. Viết theo nhóm để soát cho dễ.
     */
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',

        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',

        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',

        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',

        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',

        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',

        'đ' => 'd',
    ];

    /**
     * Chuẩn hoá một chuỗi: chữ thường, bỏ dấu, bỏ ký tự lạ, gom khoảng trắng.
     *
     * Kết quả chỉ còn a-z, 0-9 và dấu cách đơn.
     */
    public function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = mb_strtolower($value, 'UTF-8');

        /*
         * Bỏ dấu tổ hợp (Unicode NFD).
         *
         * Chữ "ố" có hai cách lưu: một ký tự dựng sẵn (U+1ED1), hoặc "o"
         * kèm hai dấu rời. Trình duyệt trên Windows gửi dạng dựng sẵn,
         * nhưng máy Mac có thể gửi dạng rời. Bảng ACCENTS chỉ xử lý được
         * dạng dựng sẵn, nên dạng rời phải gỡ dấu bằng regex ở đây —
         * thiếu bước này thì cùng một từ mà máy Mac tìm không ra.
         *
         * Chạy TRƯỚC bảng tra: gỡ dấu rời xong thì phần chữ gốc còn lại
         * đã là a-z, bảng tra không phải làm gì thêm.
         */
        $value = preg_replace('/\p{Mn}/u', '', $value) ?? $value;

        $value = strtr($value, self::ACCENTS);

        // Mọi thứ không phải chữ/số đều thành khoảng trắng: dấu câu, gạch
        // nối, emoji, ký tự nửa vời còn sót. Cách này an toàn hơn liệt kê
        // những gì cần bỏ, vì không thể liệt kê hết.
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Tách chuỗi đã chuẩn hoá thành danh sách từ, đã khử trùng và giới hạn.
     *
     * @return list<string>
     */
    public function tokenize(?string $value): array
    {
        $value = $this->normalize(mb_substr((string) $value, 0, self::MAX_QUERY_LENGTH, 'UTF-8'));

        if ($value === '') {
            return [];
        }

        $tokens = [];

        foreach (explode(' ', $value) as $token) {
            $token = substr($token, 0, self::MAX_TOKEN_LENGTH);

            // Khử trùng bằng khoá mảng: "hoa hoa hong" chỉ cần lọc "hoa"
            // một lần, thêm điều kiện WHERE giống hệt chỉ tốn công.
            if ($token !== '' && ! isset($tokens[$token])) {
                $tokens[$token] = true;
            }

            if (count($tokens) >= self::MAX_TOKENS) {
                break;
            }
        }

        return array_keys($tokens);
    }
}
