<?php

namespace App\Enums;

/** Cảm xúc dưới một bài Góc cây. */
enum CommunityReaction: string
{
    case Thich = 'thich';
    case Yeu = 'yeu';
    case Haha = 'haha';
    case Wow = 'wow';
    case Buon = 'buon';

    public function label(): string
    {
        return match ($this) {
            self::Thich => 'Thích',
            self::Yeu => 'Yêu thích',
            self::Haha => 'Haha',
            self::Wow => 'Wow',
            self::Buon => 'Buồn',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Thich => 'hand-thumbs-up-fill',
            self::Yeu => 'heart-fill',
            self::Haha => 'emoji-laughing-fill',
            self::Wow => 'emoji-surprise-fill',
            self::Buon => 'emoji-frown-fill',
        };
    }

    public function mau(): string
    {
        return 'cam-xuc--' . $this->value;
    }

    public static function macDinh(): self
    {
        return self::Thich;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
