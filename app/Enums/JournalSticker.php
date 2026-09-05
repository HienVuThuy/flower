<?php

namespace App\Enums;

/**
 * Nhãn dán gắn vào một trang nhật ký.
 * ============================================================
 * KHÔNG DÙNG EMOJI. Nhãn dán ở đây là hình vẽ SVG nằm trong mã nguồn
 * (`components/journal/sticker.blade.php`), vì ba lý do:
 *
 *   1. Emoji hiển thị khác nhau trên từng hệ điều hành — cùng một trang
 *      nhật ký, máy này ra hình này, máy kia ra hình khác.
 *   2. Emoji ăn theo màu chữ không được; SVG thì theo được màu của bộ
 *      giao diện sổ mà người dùng chọn.
 *   3. Trình đọc màn hình đọc emoji ra một cái tên tiếng Anh dài dòng.
 *
 * ============================================================
 * NHÃN DÁN CÓ NGHĨA, KHÔNG CHỈ ĐỂ ĐẸP.
 *
 * Mỗi nhãn kèm một `meaning()` — "hôm nay đã tưới", "cây ra hoa", "bị
 * sâu". Nhờ vậy nhìn lướt dòng thời gian là thấy được chuyện gì đã xảy
 * ra mà không phải đọc từng trang.
 *
 * Đó cũng là lý do bộ nhãn ĐÓNG chứ không cho tự tải lên: một bộ hình có
 * ý nghĩa chung thì đọc lướt được; một bộ hình ai thích gì dán nấy thì
 * chỉ là hình.
 */
enum JournalSticker: string
{
    case Sprout = 'sprout';
    case Bloom = 'bloom';
    case Water = 'water';
    case Sun = 'sun';
    case Fertilise = 'fertilise';
    case Repot = 'repot';
    case Prune = 'prune';
    case Pest = 'pest';
    case Wilt = 'wilt';
    case Heart = 'heart';
    case Star = 'star';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Sprout => 'Nảy mầm',
            self::Bloom => 'Ra hoa',
            self::Water => 'Đã tưới',
            self::Sun => 'Phơi nắng',
            self::Fertilise => 'Đã bón',
            self::Repot => 'Thay chậu',
            self::Prune => 'Cắt tỉa',
            self::Pest => 'Bị sâu bệnh',
            self::Wilt => 'Héo, xuống sức',
            self::Heart => 'Thích cái này',
            self::Star => 'Đáng nhớ',
            self::Note => 'Cần xem lại',
        };
    }

    /** Câu mô tả đầy đủ — dùng cho `title` và cho trình đọc màn hình. */
    public function meaning(): string
    {
        return match ($this) {
            self::Sprout => 'Cây ra mầm mới',
            self::Bloom => 'Cây ra hoa',
            self::Water => 'Hôm nay có tưới',
            self::Sun => 'Có đưa ra nắng',
            self::Fertilise => 'Có bón phân',
            self::Repot => 'Có thay chậu hoặc thay đất',
            self::Prune => 'Có cắt tỉa',
            self::Pest => 'Phát hiện sâu bệnh',
            self::Wilt => 'Cây héo hoặc xuống sức',
            self::Heart => 'Ngày đáng yêu thích',
            self::Star => 'Mốc đáng nhớ',
            self::Note => 'Ghi chú cần xem lại sau',
        };
    }

    /**
     * Nhóm để xếp trong bảng chọn.
     *
     * Mười hai hình xếp thành một dãy dài thì phải quét mắt cả dãy mới
     * tìm được cái cần. Chia ba nhóm theo việc — chăm sóc, biến chuyển,
     * đánh dấu — thì tìm bằng cách nghĩ chứ không bằng cách nhìn.
     */
    public function group(): string
    {
        return match ($this) {
            self::Water, self::Sun, self::Fertilise, self::Repot, self::Prune => 'Việc đã làm',
            self::Sprout, self::Bloom, self::Pest, self::Wilt => 'Cây thay đổi',
            self::Heart, self::Star, self::Note => 'Đánh dấu',
        };
    }

    /**
     * Nhãn dán nào hợp với loại sổ nào.
     *
     * Sổ theo dõi giá không cần "đã tưới" hay "thay chậu"; bày ra cả bộ ở
     * đó là bắt người dùng lọc bằng mắt qua chín hình vô nghĩa để tìm ba
     * hình dùng được.
     *
     * @return list<self>
     */
    public static function forKind(JournalKind $kind): array
    {
        return match ($kind) {
            JournalKind::Growth => self::cases(),

            JournalKind::Analysis => [
                self::Pest, self::Wilt, self::Bloom, self::Sprout,
                self::Water, self::Fertilise, self::Repot, self::Prune,
                self::Note, self::Star,
            ],

            // Sổ giá và sổ mục tiêu chỉ cần đánh dấu, không cần việc
            // chăm cây.
            JournalKind::Price, JournalKind::Goal => [
                self::Heart, self::Star, self::Note,
            ],

            JournalKind::Free => self::cases(),
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
