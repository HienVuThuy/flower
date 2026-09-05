<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Danh sách thiết bị đang đăng nhập, và cách đá từng thiết bị ra.
 * ============================================================
 * VÌ SAO CẦN: khách nghi tài khoản bị người khác dùng thì hiện chỉ có
 * một cách duy nhất là đổi mật khẩu — vừa nặng tay, vừa không cho họ
 * biết có thật sự đang bị dùng hay không. Màn hình này trả lời câu hỏi
 * "ngoài mình ra còn ai đang đăng nhập?" và cho cắt riêng từng phiên.
 *
 * ĐỌC THẲNG BẢNG `sessions` — chỉ chạy được với SESSION_DRIVER=database.
 * Driver file/redis không lưu user_id nên không có cách nào liệt kê phiên
 * theo người dùng; lúc đó hàm trả về danh sách rỗng chứ không nổ, và
 * giao diện tự nói rõ là không xem được (xem ProfileController).
 *
 * KHÔNG DÙNG THƯ VIỆN NHẬN DẠNG TRÌNH DUYỆT. Chuỗi User-Agent là thứ do
 * trình duyệt tự khai và ai cũng sửa được, nên mọi kết quả đều là phỏng
 * đoán. Một bảng tra ngắn, viết rõ ra, cho kết quả đủ dùng để khách nhận
 * ra máy của mình — mà không kéo thêm một phụ thuộc phải cập nhật mãi.
 *
 * VÀ ĐÂY LÀ GIỚI HẠN THẬT SỰ, KHÔNG PHẢI THIẾU SÓT SỬA ĐƯỢC:
 *
 * Nhiều trình duyệt Chromium CỐ Ý giấu tên mình để khỏi bị các trang web
 * chặn nhầm. Cốc Cốc bản mới gửi đúng chuỗi của Chrome, không còn token
 * `coc_coc_browser` nào. Kiểm chứng trên chính bảng `sessions` của hệ
 * thống này — phiên của một máy đang chạy Cốc Cốc khai:
 *
 *   Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36
 *   (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36
 *
 * Không có mẩu nào phân biệt được với Chrome thật. Thư viện nhận dạng
 * đắt tiền nhất cũng chỉ đọc đúng chuỗi đó, nên cũng trả lời "Chrome".
 *
 * Vì vậy bảng tra dưới đây vẫn giữ tên riêng của các trình duyệt CÓ khai
 * (bản cũ của Cốc Cốc, Brave, Vivaldi, Samsung…), còn giao diện thì nói
 * rõ đây là phỏng đoán và chỉ về hai thứ đáng tin hơn để khách tự đối
 * chiếu: ĐỊA CHỈ IP và LẦN HOẠT ĐỘNG GẦN NHẤT.
 */
class ActiveSessions
{
    /**
     * Bảng tra hệ điều hành. THỨ TỰ QUAN TRỌNG.
     *
     * Chuỗi User-Agent của Android LUÔN chứa cả "Linux", và của iPad/
     * iPhone chứa cả "Mac OS X". Xét từ cụ thể nhất tới chung nhất, nếu
     * không thì mọi điện thoại Android đều bị gọi là máy Linux.
     */
    private const PLATFORMS = [
        'Android' => 'Android',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Windows NT' => 'Windows',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    /**
     * Bảng tra trình duyệt. THỨ TỰ CŨNG QUAN TRỌNG, và còn dễ sai hơn.
     *
     * Gần như mọi trình duyệt hiện nay đều dựng trên Chromium và khai
     * ĐÈ LÊN NHAU: Cốc Cốc khai cả "Chrome" lẫn "Safari"; Edge khai cả
     * "Chrome" lẫn "Safari"; Chrome khai cả "Safari". Nên phải hỏi từ
     * cái RIÊNG NHẤT tới cái CHUNG NHẤT — đảo lại thì mọi trình duyệt
     * trên đời đều thành Safari.
     *
     * Các tên riêng (coc_coc_browser, Brave, Vivaldi…) phải đứng TRƯỚC
     * 'Chrome/' vì chuỗi của chúng đều chứa 'Chrome/'.
     */
    private const BROWSERS = [
        'coc_coc_browser/' => 'Cốc Cốc',
        'CocCoc/' => 'Cốc Cốc',
        'Vivaldi/' => 'Vivaldi',
        'YaBrowser/' => 'Yandex',
        'SamsungBrowser/' => 'Samsung Internet',
        'UCBrowser/' => 'UC Browser',
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Firefox/' => 'Firefox',
        'Brave/' => 'Brave',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    /**
     * Các phiên đang mở của một người dùng, mới hoạt động nhất lên đầu.
     *
     * @return Collection<int, array{id: string, current: bool, device: string, ip: string, lastActive: Carbon}>
     */
    public function forUser(User $user, ?string $currentSessionId = null): Collection
    {
        if (! $this->supported()) {
            return collect();
        }

        return collect(
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                // KHÔNG lấy cột `payload`: nó chứa toàn bộ dữ liệu phiên
                // (giỏ hàng, token, dữ liệu biểu mẫu đang dở). Màn hình
                // này không cần tới, và không kéo lên thì không có đường
                // nào lộ ra.
                ->select(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->orderByDesc('last_activity')
                ->get()
        )->map(fn ($row) => [
            'id' => $row->id,
            'current' => $currentSessionId !== null && $row->id === $currentSessionId,
            'device' => $this->describe($row->user_agent),
            'ip' => $row->ip_address ?: 'không rõ',
            'lastActive' => Carbon::createFromTimestamp($row->last_activity),
        ]);
    }

    /**
     * Đá một phiên cụ thể.
     *
     * BẮT BUỘC lọc theo user_id, không chỉ theo id phiên. Id phiên đi qua
     * biểu mẫu nên người gửi sửa được thành bất kỳ chuỗi nào; thiếu điều
     * kiện user_id thì ai cũng đá được phiên của người khác chỉ bằng cách
     * đoán đúng một id.
     *
     * @return bool có xoá được phiên nào không
     */
    public function revoke(User $user, string $sessionId): bool
    {
        if (! $this->supported()) {
            return false;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /** Trình điều khiển phiên hiện tại có liệt kê được không. */
    public function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Mô tả thiết bị từ chuỗi User-Agent. Chỉ là phỏng đoán.
     */
    private function describe(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Không rõ thiết bị';
        }

        $platform = null;
        $browser = null;

        foreach (self::PLATFORMS as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                $platform = $label;

                break;
            }
        }

        foreach (self::BROWSERS as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                $browser = $label;

                break;
            }
        }

        return match (true) {
            $browser && $platform => "{$browser} trên {$platform}",
            (bool) $browser => $browser,
            (bool) $platform => $platform,
            default => 'Không rõ thiết bị',
        };
    }
}
