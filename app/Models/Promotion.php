<?php

namespace App\Models;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'banner',
        'theme_key',
        'type',
        'discount_value',
        'starts_at',
        'ends_at',
        'daily_start_time',
        'daily_end_time',
        'weekdays',
        'status',
        'priority',
    ];

    protected $casts = [
        'type' => PromotionType::class,
        'status' => PromotionStatus::class,
        'discount_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        /*
         * KHÔNG cast daily_start_time / daily_end_time sang datetime.
         *
         * Chúng là GIỜ TRONG NGÀY, không phải một mốc thời gian. Cast
         * sang datetime thì Carbon gắn thêm ngày 01/01/1970 vào và mọi
         * phép so sánh với "bây giờ" đều sai. Giữ nguyên chuỗi "HH:MM:SS"
         * rồi so chuỗi — xem isWithinDailyWindow().
         */
        'weekdays' => 'array',
        'priority' => 'integer',
    ];

    /**
     * Chỉ rõ tên bảng pivot — Laravel mặc định suy ra
     * `product_promotion`, bảng thực tế là `promotion_product`.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')
            ->withPivot(['discount_type', 'discount_value', 'promotional_price'])
            ->withTimestamps();
    }

    /**
     * Chỉ những chương trình ĐANG thực sự áp dụng cho khách.
     *
     * Cần cả hai điều kiện:
     *  - admin đã đặt status = Active (ý định), và
     *  - thời điểm hiện tại nằm trong khoảng hiệu lực.
     *
     * starts_at/ends_at để trống nghĩa là "không giới hạn phía đó".
     */
    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', PromotionStatus::Active)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /** Đang áp dụng hay không — dùng cho một instance đã tải sẵn. */
    public function isRunning(): bool
    {
        if ($this->status !== PromotionStatus::Active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        /*
         * GIÁ LINH HOẠT — hai điều kiện lặp lại theo chu kỳ.
         *
         * Đặt SAU các phép kiểm tra khoảng ngày: chương trình phải còn
         * trong hạn trước đã, rồi mới xét tới giờ và thứ. Đảo thứ tự thì
         * một chương trình đã kết thúc vẫn "đang chạy" vào đúng khung giờ
         * của nó.
         */
        if (! $this->isOnActiveWeekday($now)) {
            return false;
        }

        return $this->isWithinDailyWindow($now);
    }

    /**
     * Hôm nay có nằm trong các thứ đã khai không.
     *
     * Không khai thứ nào = mọi ngày. Đó là hành vi cũ, nên chương trình
     * tạo trước khi có cột này chạy y như trước.
     */
    public function isOnActiveWeekday(?\Illuminate\Support\Carbon $now = null): bool
    {
        if (! is_array($this->weekdays) || $this->weekdays === []) {
            return true;
        }

        // ISO-8601: 1 = Thứ Hai ... 7 = Chủ Nhật. Dùng chuẩn này chứ
        // không dùng dayOfWeek của PHP (0 = Chủ Nhật) — 0 là giá trị dễ
        // bị hiểu nhầm thành "chưa đặt" khi lưu vào JSON.
        return in_array(($now ?? now())->isoWeekday(), array_map('intval', $this->weekdays), true);
    }

    /**
     * Bây giờ có nằm trong khung giờ trong ngày không.
     *
     * SO SÁNH CHUỖI "HH:MM:SS" — hợp lệ vì định dạng có độ dài cố định
     * và đã đệm số 0, nên thứ tự chuỗi trùng với thứ tự thời gian.
     *
     * XỬ LÝ KHUNG GIỜ QUA NỬA ĐÊM: "22:00 → 02:00" có giờ bắt đầu LỚN
     * HƠN giờ kết thúc. Bỏ qua trường hợp này thì khung đó không bao giờ
     * đúng, và admin sẽ tưởng chương trình hỏng.
     */
    public function isWithinDailyWindow(?\Illuminate\Support\Carbon $now = null): bool
    {
        $from = $this->daily_start_time;
        $to = $this->daily_end_time;

        // Chỉ khai một đầu thì coi như không giới hạn: nửa điều kiện là
        // cấu hình dở dang, không nên tự đoán ý admin.
        if (! $from || ! $to) {
            return true;
        }

        $current = ($now ?? now())->format('H:i:s');

        return $from <= $to
            ? ($current >= $from && $current <= $to)
            // Qua nửa đêm: đúng khi ở NỬA SAU của ngày hôm trước hoặc
            // nửa đầu của ngày hôm sau.
            : ($current >= $from || $current <= $to);
    }

    /**
     * Mô tả khung thời gian cho người đọc, hoặc null nếu chạy liên tục.
     *
     * Dùng ở cả trang quản trị lẫn trang khách: khách phải biết ưu đãi
     * này chỉ có trong khung giờ nào, nếu không họ quay lại lúc 10h sáng
     * và thấy giá khác — mà không có lời giải thích nào.
     */
    public function scheduleText(): ?string
    {
        $parts = [];

        if ($this->daily_start_time && $this->daily_end_time) {
            $parts[] = sprintf(
                '%s–%s hằng ngày',
                substr($this->daily_start_time, 0, 5),
                substr($this->daily_end_time, 0, 5),
            );
        }

        if (is_array($this->weekdays) && $this->weekdays !== []) {
            $names = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

            $parts[] = 'chỉ '.implode(', ', array_map(
                fn ($d) => $names[(int) $d] ?? (string) $d,
                $this->weekdays,
            ));
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Số ngày còn lại, dùng cho nhãn "Còn N ngày" ở trang sản phẩm.
     * Trả null khi chương trình không có ngày kết thúc.
     */
    public function daysRemaining(): ?int
    {
        if (! $this->ends_at) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->ends_at->startOfDay(), false));
    }

    /**
     * MỨC GIẢM CAO NHẤT của chương trình, viết cho khách đọc.
     * ============================================================
     * Thanh thông báo trước đây chỉ in tên chương trình ("Giáng sinh an
     * lành – Ưu đãi ngập tràn") — nghe hay nhưng KHÔNG NÓI GÌ. Khách
     * không biết có đáng bấm vào không, nên phần lớn là không bấm.
     *
     * "Giảm đến 30%" là con số thật, tính từ chính dữ liệu chương trình.
     *
     * VÌ SAO LÀ "ĐẾN", KHÔNG PHẢI MỘT CON SỐ CỐ ĐỊNH: mỗi sản phẩm trong
     * chương trình có thể có mức riêng (cột pivot). Nói "giảm 30%" khi
     * chỉ một sản phẩm được 30% còn lại 10% là hứa quá lời. "Đến" là
     * cách nói đúng và cũng là cách các trang thật dùng.
     *
     * TRẢ VỀ null khi không quy về một con số được (Combo, mua X tặng Y,
     * hoặc mỗi sản phẩm một kiểu giảm khác nhau) — lúc đó giao diện chỉ
     * hiện tên chương trình, không bịa ra con số.
     */
    public function headlineDiscount(): ?string
    {
        /*
         * Cần quan hệ `products` đã nạp kèm pivot. Chưa nạp thì nạp —
         * hàm này chỉ được gọi MỘT LẦN cho chương trình nổi bật trên mỗi
         * trang, nên không có nguy cơ N+1.
         */
        $this->loadMissing('products');

        $percents = [];
        $amounts = [];

        foreach ($this->products as $product) {
            $pivot = $product->pivot;

            $type = $pivot?->discount_type
                ? PromotionType::tryFrom($pivot->discount_type)
                : $this->type;

            $value = $pivot?->discount_value ?? $this->discount_value;

            if ($type === null || $value === null) {
                continue;
            }

            match ($type) {
                PromotionType::Percent => $percents[] = (float) $value,
                PromotionType::FixedAmount => $amounts[] = (float) $value,
                /*
                 * FixedPrice là "giá chỉ còn X", không phải "giảm X".
                 * Quy nó về mức giảm cần biết giá gốc của từng sản phẩm,
                 * và mỗi sản phẩm ra một con số khác nhau — không gộp
                 * thành một câu tiêu đề được. Bỏ qua, đúng hơn là bịa.
                 */
                default => null,
            };
        }

        if ($percents !== []) {
            return 'Giảm đến '.rtrim(rtrim(number_format(max($percents), 1, ',', '.'), '0'), ',').'%';
        }

        if ($amounts !== []) {
            return 'Giảm đến '.number_format(max($amounts), 0, ',', '.').'đ';
        }

        return null;
    }

    /**
     * Thời gian còn lại, viết cho khách đọc — và nói ĐÚNG mức cấp bách.
     * ============================================================
     * "còn 7 ngày" thì không ai vội. "Còn 3 giờ" thì có. Trước đây mọi
     * mốc đều quy về ngày, nên chương trình kết thúc sau hai tiếng vẫn
     * hiện "còn 0 ngày" — vừa khó hiểu vừa mất hết tính cấp bách.
     *
     * KHÔNG bịa cấp bách khi không có: chương trình không đặt ngày kết
     * thúc thì trả về null và giao diện không hiện gì cả.
     */
    public function endsInText(): ?string
    {
        if (! $this->ends_at) {
            return null;
        }

        $now = now();

        if ($now->gt($this->ends_at)) {
            return null;
        }

        $hours = $now->diffInHours($this->ends_at);

        if ($hours < 1) {
            return 'sắp kết thúc';
        }

        if ($hours < 24) {
            return 'còn '.(int) $hours.' giờ';
        }

        $days = (int) $now->startOfDay()->diffInDays($this->ends_at->startOfDay(), false);

        return $days <= 1 ? 'hôm nay là ngày cuối' : "còn {$days} ngày";
    }

    /**
     * Trạng thái hiển thị cho admin — phản ánh cả thời gian, không
     * chỉ cột status. Giúp admin thấy ngay "đã lên lịch nhưng chưa
     * tới ngày" hay "đang Active nhưng đã quá hạn".
     */
    public function effectiveStatus(): PromotionStatus
    {
        if ($this->status !== PromotionStatus::Active) {
            return $this->status;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return PromotionStatus::Scheduled;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return PromotionStatus::Ended;
        }

        return PromotionStatus::Active;
    }
}
