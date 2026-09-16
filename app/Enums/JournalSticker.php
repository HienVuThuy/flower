<?php

namespace App\Enums;

/** Nhãn dán gắn vào một trang nhật ký. */
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

    public function group(): string
    {
        return match ($this) {
            self::Water, self::Sun, self::Fertilise, self::Repot, self::Prune => 'Việc đã làm',
            self::Sprout, self::Bloom, self::Pest, self::Wilt => 'Cây thay đổi',
            self::Heart, self::Star, self::Note => 'Đánh dấu',
        };
    }

    public static function forKind(JournalKind $kind): array
    {
        return match ($kind) {
            JournalKind::Growth => self::cases(),

            JournalKind::Analysis => [
                self::Pest, self::Wilt, self::Bloom, self::Sprout,
                self::Water, self::Fertilise, self::Repot, self::Prune,
                self::Note, self::Star,
            ],

            JournalKind::Price, JournalKind::Goal => [
                self::Heart, self::Star, self::Note,
            ],

            JournalKind::Free => self::cases(),
        };
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
