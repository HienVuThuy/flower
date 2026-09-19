<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Chat\ChatException;
use App\Services\Chat\LiveChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Nhân viên trả lời tin nhắn của khách từ khung chat trong trang quản trị. */
class ChatController extends Controller
{
    public function __construct(
        private readonly LiveChat $chat,
    ) {
    }

    public function users(Request $request): JsonResponse
    {
        $tim = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;

        return response()->json([
            'hoi_thoai' => $this->chat->hopThu($tim),
            'chua_doc' => $this->chat->cuaHangChuaDoc(),
        ]);
    }

    public function messages(Request $request, User $user): JsonResponse
    {
        $khach = $this->khach($user);
        $sau = max(0, (int) $request->query('sau', 0));

        $this->chat->cuaHangDaDoc($khach);

        return response()->json([
            'khach' => ['id' => $khach->id, 'ten' => $khach->name, 'email' => $khach->email],
            'tin' => $this->chat->tinCua($khach, $sau)->map(fn ($t) => $this->chat->json($t))->values(),
        ]);
    }

    public function send(Request $request, User $user): JsonResponse
    {
        $khach = $this->khach($user);

        $data = $request->validate(
            ['noi_dung' => ['required', 'string', 'max:' . LiveChat::DO_DAI_TOI_DA]],
            [],
            ['noi_dung' => 'tin nhắn'],
        );

        try {
            $tin = $this->chat->gui(Auth::user(), $khach, $data['noi_dung']);
        } catch (ChatException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['tin' => $this->chat->json($tin->load('sender'))], 201);
    }

    private function khach(User $user): User
    {
        abort_unless($user->role === UserRole::Customer, 404);

        return $user;
    }
}
