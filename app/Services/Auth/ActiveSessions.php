<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Danh sách thiết bị đang đăng nhập, và cách đá từng thiết bị ra. */
class ActiveSessions
{
    private const PLATFORMS = [
        'Android' => 'Android',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Windows NT' => 'Windows',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

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

    public function forUser(User $user, ?string $currentSessionId = null): Collection
    {
        if (! $this->supported()) {
            return collect();
        }

        return collect(
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
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

    public function supported(): bool
    {
        return config('session.driver') === 'database';
    }

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
