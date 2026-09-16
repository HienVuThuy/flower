<?php

namespace App\Enums;

/** Loại sổ nhật ký cá nhân. */
enum JournalKind: string
{
    case Growth = 'growth';
    case Goal = 'goal';
    case Price = 'price';
    case Analysis = 'analysis';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::Growth => 'Nhật ký sinh trưởng',
            self::Goal => 'Mục tiêu',
            self::Price => 'Theo dõi giá',
            self::Analysis => 'Phân tích cây',
            self::Free => 'Ghi chép tự do',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Growth => 'Ghi lại cây lớn thế nào theo thời gian: chiều cao, số lá, ảnh từng đợt.',
            self::Goal => 'Đặt một đích cụ thể kèm hạn, rồi ghi tiến độ dần tới đó.',
            self::Price => 'Ghi giá thấy được ở từng thời điểm, để biết lúc nào nên mua.',
            self::Analysis => 'Quan sát và suy đoán: cây bị gì, thử cách nào, kết quả ra sao.',
            self::Free => 'Không khuôn mẫu. Ghi gì cũng được, thêm chỉ số nào cũng được.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Growth => 'flower2',
            self::Goal => 'check-circle',
            self::Price => 'tags',
            self::Analysis => 'search',
            self::Free => 'list',
        };
    }

    public function suggestedMetrics(): array
    {
        return match ($this) {
            self::Growth => [
                'Chiều cao' => 'cm',
                'Số lá' => 'lá',
                'Đường kính tán' => 'cm',
            ],
            self::Goal => [
                'Tiến độ' => '%',
            ],
            self::Price => [
                'Giá' => \App\Services\Shop\Money::symbol(),
            ],
            self::Analysis => [
                'Độ ẩm đất' => '%',
                'Số giờ nắng' => 'giờ',
            ],
            self::Free => [],
        };
    }

    public function defaultChartMetric(): ?string
    {
        return array_key_first($this->suggestedMetrics());
    }

    public function panels(): array
    {
        return match ($this) {
            self::Growth => ['goal', 'chart', 'photo-strip', 'care-summary', 'timeline'],
            self::Goal => ['goal', 'milestones', 'chart', 'timeline'],
            self::Price => ['price-stats', 'chart', 'price-table'],
            self::Analysis => ['rating', 'findings', 'chart', 'timeline'],
            self::Free => ['chart', 'timeline'],
        };
    }

    public function entryFields(): array
    {
        return match ($this) {
            self::Growth => ['date', 'title', 'body', 'condition', 'care', 'photo', 'sticker', 'metrics'],
            self::Goal => ['date', 'title', 'body', 'sticker', 'metrics'],
            self::Price => ['date', 'price', 'place', 'body', 'sticker'],
            self::Analysis => ['date', 'title', 'rating', 'good', 'bad', 'body', 'condition', 'photo', 'sticker', 'metrics'],
            self::Free => ['date', 'title', 'body', 'photo', 'sticker', 'metrics'],
        };
    }

    public function hasField(string $field): bool
    {
        return in_array($field, $this->entryFields(), true);
    }

    public function hasPanel(string $panel): bool
    {
        return in_array($panel, $this->panels(), true);
    }

    public function dataFields(): array
    {
        return match ($this) {
            self::Growth => [
                'care' => ['nullable', 'array', 'max:8'],
            ],
            self::Price => [
                'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
                'place' => ['nullable', 'string', 'max:120'],
            ],
            self::Analysis => [
                'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
                'good' => ['nullable', 'string', 'max:500'],
                'bad' => ['nullable', 'string', 'max:500'],
            ],
            self::Goal, self::Free => [],
        };
    }

    public function usesMilestones(): bool
    {
        return $this === self::Goal;
    }

    public function entryWords(): array
    {
        return match ($this) {
            self::Growth => [
                'one' => 'lần ghi',
                'add' => 'Ghi thêm một lần',
                'empty' => 'Chưa ghi lần nào',
            ],
            self::Goal => [
                'one' => 'lần cập nhật',
                'add' => 'Cập nhật tiến độ',
                'empty' => 'Chưa cập nhật lần nào',
            ],
            self::Price => [
                'one' => 'lần khảo giá',
                'add' => 'Ghi một lần khảo giá',
                'empty' => 'Chưa khảo giá lần nào',
            ],
            self::Analysis => [
                'one' => 'lần quan sát',
                'add' => 'Ghi một lần quan sát',
                'empty' => 'Chưa quan sát lần nào',
            ],
            self::Free => [
                'one' => 'ghi chép',
                'add' => 'Viết thêm',
                'empty' => 'Chưa viết gì',
            ],
        };
    }

    public function defaultTheme(): JournalTheme
    {
        return match ($this) {
            self::Growth => JournalTheme::Leaf,
            self::Goal => JournalTheme::Dusk,
            self::Price => JournalTheme::Paper,
            self::Analysis => JournalTheme::Sand,
            self::Free => JournalTheme::Moss,
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
