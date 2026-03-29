<?php

namespace Romansh\LaravelCreemAgent\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramMessageSender
{
    public function send(string $message, mixed $chatId = null): void
    {
        $token = config('creem-agent.notifications.telegram_bot_token');
        $chatId ??= config('creem-agent.notifications.telegram_chat_id');

        if (!$token || !$chatId) {
            return;
        }

        $text = trim($message);

        if ($text === '') {
            return;
        }

        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3997).'...';
        }

        try {
            $base = config('creem-agent.notifications.telegram_api_base', 'https://api.telegram.org');

            $response = Http::post(rtrim($base, '/') . "/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            if (!$response->successful()) {
                Log::error('Telegram sendMessage returned non-success response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage failed: '.$e->getMessage());
        }
    }
}