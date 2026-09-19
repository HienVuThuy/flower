<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatException;
use App\Services\Chat\LiveChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Khách nhắn tin với cửa hàng: trang riêng + khung chat nổi dùng chung các đường này. */
class ChatController extends Controller
{
    public function __construct(
        private readonly LiveChat $chat,
    ) {
    }

    public function index(): View
    {
        $khach = Auth::user();
        $this->chat->khachDaDoc($khach);

        return view('shop.chat.index', [
            'tinNhan' => $this->chat->tinCua($khach)->map(fn ($t) => $this->chat->json($t, choKhach: true)),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $khach = Auth::user();
        $sau = max(0, (int) $request->query('sau', 0));

        if ($request->boolean('da_xem')) {
            $this->chat->khachDaDoc($khach);
        }

        return response()->json([
            'tin' => $this->chat->tinCua($khach, $sau)->map(fn ($t) => $this->chat->json($t, choKhach: true))->values(),
            'chua_doc' => $this->chat->khachChuaDoc($khach),
        ]);
    }

    public function unread(): JsonResponse
    {
        return response()->json(['chua_doc' => $this->chat->khachChuaDoc(Auth::user())]);
    }

    public function send(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(
            ['noi_dung' => ['required', 'string', 'max:' . LiveChat::DO_DAI_TOI_DA]],
            [],
            ['noi_dung' => 'tin nhắn'],
        );

        try {
            $tin = $this->chat->gui(Auth::user(), Auth::user(), $data['noi_dung']);
        } catch (ChatException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage())->withInput();
        }

        return $request->expectsJson()
            ? response()->json(['tin' => $this->chat->json($tin->load('sender'), choKhach: true)], 201)
            : redirect()->route('shop.chat.index');
    }
}
