<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\AI\AiException;
use App\Services\AI\ShoppingAdvisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Khung chat trợ lý AI. */
class AiChatController extends Controller
{
    public function store(Request $request, ShoppingAdvisor $troLy): JsonResponse
    {
        if (! $troLy->configured()) {
            return response()->json(['loi' => 'Trợ lý AI chưa được cấu hình.'], 503);
        }

        $data = $request->validate([
            'cau_hoi' => ['required', 'string', 'max:' . (int) config('ai.max_message_length', 500)],
        ], [], ['cau_hoi' => 'câu hỏi']);

        try {
            $traLoi = $troLy->hoi($data['cau_hoi'], $request->user());
        } catch (AiException $e) {
            return response()->json(['loi' => $e->getMessage()], 502);
        }

        return response()->json(['tra_loi' => $traLoi]);
    }

    public function destroy(ShoppingAdvisor $troLy): JsonResponse
    {
        $troLy->xoaLichSu();

        return response()->json(['ok' => true]);
    }
}
