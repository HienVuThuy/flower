<?php

namespace App\Services\Chat;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Shop\StoreProfile;
use App\Services\Time\Gio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Tin nhắn trực tiếp khách ↔ cửa hàng — NƠI DUY NHẤT ghi và đọc. */
class LiveChat
{
    public const DO_DAI_TOI_DA = 1000;

    private const MOI_LAN_TAI = 100;

    /** $phieu: tin nhắn trao đổi về một phiếu chăm hộ — vẫn nằm trong hộp thư chat chung, có gắn mã phiếu. */
    public function gui(User $nguoiGui, User $khach, string $noiDung, ?\App\Models\BoardingBooking $phieu = null): Message
    {
        $noiDung = trim($noiDung);

        if ($noiDung === '') {
            throw new ChatException('Tin nhắn không được để trống.');
        }

        if (mb_strlen($noiDung) > self::DO_DAI_TOI_DA) {
            throw new ChatException('Tin nhắn dài tối đa ' . self::DO_DAI_TOI_DA . ' ký tự.');
        }

        if ($khach->role !== UserRole::Customer) {
            throw new ChatException('Chỉ nhắn tin được với tài khoản khách hàng.');
        }

        $tuKhach = $nguoiGui->is($khach);

        if (! $tuKhach && ! $nguoiGui->duoc(\App\Enums\Quyen::HoTro) && ! ($phieu && $nguoiGui->duoc(\App\Enums\Quyen::DonHang))) {
            throw new ChatException('Tài khoản của bạn không có quyền trả lời tin nhắn.');
        }

        if ($phieu && (int) $phieu->user_id !== (int) $khach->id) {
            throw new ChatException('Phiếu này không phải của khách đang nhắn.');
        }

        $tin = new Message();
        $tin->forceFill([
            'customer_id' => $khach->id,
            'sender_id' => $nguoiGui->id,
            'boarding_booking_id' => $phieu?->id,
            'content' => $noiDung,
        ])->save();

        if (! $tuKhach) {
            $this->baoKhach($khach, $noiDung);
        }

        return $tin;
    }

    public function tinCua(User $khach, int $sauId = 0): Collection
    {
        return Message::query()
            ->where('customer_id', $khach->id)
            ->when($sauId > 0, fn ($q) => $q->where('id', '>', $sauId))
            ->with(['sender:id,name,role', 'boardingBooking:id,code'])
            ->orderByDesc('id')
            ->limit(self::MOI_LAN_TAI)
            ->get()
            ->reverse()
            ->values();
    }

    public function khachDaDoc(User $khach): void
    {
        Message::where('customer_id', $khach->id)
            ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $khach->id))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        UserNotification::where('user_id', $khach->id)
            ->where('type', NotificationType::TinNhan->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function cuaHangDaDoc(User $khach): void
    {
        Message::where('customer_id', $khach->id)
            ->where('sender_id', $khach->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function khachChuaDoc(User $khach): int
    {
        return Message::where('customer_id', $khach->id)
            ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $khach->id))
            ->whereNull('read_at')
            ->count();
    }

    public function cuaHangChuaDoc(): int
    {
        return Message::whereColumn('sender_id', 'customer_id')->whereNull('read_at')->count();
    }

    public function hopThu(?string $tim = null, int $toiDa = 50): Collection
    {
        $cuoi = Message::query()
            ->selectRaw('customer_id, MAX(id) as tin_cuoi')
            ->selectRaw('SUM(CASE WHEN sender_id = customer_id AND read_at IS NULL THEN 1 ELSE 0 END) as chua_doc')
            ->groupBy('customer_id');

        $dong = DB::query()
            ->fromSub($cuoi, 'h')
            ->join('users', 'users.id', '=', 'h.customer_id')
            ->join('messages', 'messages.id', '=', 'h.tin_cuoi')
            ->when($tim !== null && trim($tim) !== '', function ($q) use ($tim) {
                $tu = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($tim)) . '%';
                $q->where(fn ($w) => $w->where('users.name', 'like', $tu)->orWhere('users.email', 'like', $tu));
            })
            ->orderByDesc('h.tin_cuoi')
            ->limit($toiDa)
            ->get([
                'users.id', 'users.name', 'users.email',
                'messages.content', 'messages.sender_id', 'messages.created_at',
                'h.chua_doc',
            ]);

        return $dong->map(fn ($d) => [
            'id' => (int) $d->id,
            'ten' => $d->name,
            'email' => $d->email,
            'tin_cuoi' => mb_strimwidth((string) $d->content, 0, 70, '…'),
            'tin_cuoi_tu_khach' => (int) $d->sender_id === (int) $d->id,
            'luc' => Gio::hien($d->created_at)?->format('d/m H:i'),
            'chua_doc' => (int) $d->chua_doc,
        ]);
    }

    public function json(Message $tin, bool $choKhach = false): array
    {
        $tenCuaHang = StoreProfile::name();

        return [
            'id' => $tin->id,
            'noi_dung' => $tin->content,
            'tu_khach' => $tin->tuKhach(),
            'nguoi_gui' => match (true) {
                $tin->tuKhach() => $choKhach ? 'Bạn' : ($tin->sender?->name ?? 'Khách'),
                $choKhach => $tenCuaHang,
                default => $tin->sender?->name ?? $tenCuaHang,
            },
            'luc' => Gio::hien($tin->created_at)?->format('d/m H:i'),
            'da_doc' => $tin->read_at !== null,
            'phieu' => $tin->boardingBooking?->code,
        ];
    }

    private function baoKhach(User $khach, string $noiDung): void
    {
        $chuaDoc = UserNotification::where('user_id', $khach->id)
            ->where('type', NotificationType::TinNhan->value)
            ->whereNull('read_at')
            ->first();

        $trich = mb_strimwidth($noiDung, 0, 190, '…');

        if ($chuaDoc) {
            $chuaDoc->forceFill(['note' => $trich, 'created_at' => now()])->save();

            return;
        }

        $tb = new UserNotification();
        $tb->forceFill([
            'user_id' => $khach->id,
            'type' => NotificationType::TinNhan,
            'note' => $trich,
        ])->save();
    }
}
