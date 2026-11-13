<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CallOwnerController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram chưa được cấu hình.',
            ], 500);
        }

        $ip = $request->ip();

        $text = "🔔 KHÁCH GỌI CHỦ QUÁN\n\n"
            . "👤 Khách: {$validated['name']}\n"
            . "💬 Nội dung: {$validated['message']}\n"
            . "🌐 IP: {$ip}\n"
            . "🕒 Thời gian: " . now()->format('d/m/Y H:i:s');

        try {
            $response = Http::timeout(15)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]
            );

            if (!$response->successful() || !$response->json('ok')) {
                Log::error('Telegram call owner failed', [
                    'response' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Không gửi được thông báo. Vui lòng thử lại.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã gửi yêu cầu đến chủ quán!',
            ]);

        } catch (\Throwable $e) {
            Log::error('Telegram call owner exception', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể kết nối Telegram.',
            ], 500);
        }
    }
}
