<?php

namespace Romansh\LaravelCreemAgent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private ?\Closure $forwarder = null,
        private ?\Closure $telegramSender = null,
    ) {}

    public function handle(Request $request)
    {
        $token = config('creem-agent.notifications.telegram_bot_token');
        $defaultChat = config('creem-agent.notifications.telegram_chat_id');

        $text = $this->extractText($request);
        $chatId = $this->extractChatId($request, $defaultChat);

        if (empty($text)) {
            return response()->json(['ok' => false, 'reason' => 'no message'], 400);
        }

        try {
            $content = $this->forwardToAgent($text, $chatId);
        } catch (\Throwable $e) {
            Log::error('Failed forwarding Telegram message to agent: '.$e->getMessage());
            $content = '';
        }

        $reply = $this->extractReply($content);
        $this->sendReply($token, $chatId, $reply);

        return response()->json(['ok' => true]);
    }

    private function extractText(Request $request): string
    {
        return $request->input('message.text')
            ?: $request->input('edited_message.text')
            ?: $request->input('channel_post.text')
            ?: $request->input('callback_query.data')
            ?: '';
    }

    private function extractChatId(Request $request, mixed $defaultChat): mixed
    {
        return $request->input('message.chat.id')
            ?: $request->input('chat.id')
            ?: $defaultChat;
    }

    private function forwardToAgent(string $text, mixed $chatId): string
    {
        if ($this->forwarder !== null) {
            return (string) ($this->forwarder)($text, $chatId);
        }

        $agentRequest = Request::create('/creem-agent/chat', 'POST', [
            'message' => $text,
            'source' => 'telegram',
            'chat' => $chatId,
        ]);

        $response = app()->handle($agentRequest);

        return (string) $response->getContent();
    }

    private function extractReply(string $content): string
    {
        $reply = $content;
        $json = json_decode($content, true);

        if (!is_array($json)) {
            return $reply;
        }

        if (!empty($json['reply'])) {
            return $json['reply'];
        }

        if (!empty($json['message'])) {
            return $json['message'];
        }

        return (string) json_encode($json);
    }

    private function sendReply(?string $token, mixed $chatId, string $reply): void
    {
        if (!$token || !$chatId) {
            return;
        }

        try {
            if ($this->telegramSender !== null) {
                ($this->telegramSender)($token, $chatId, $reply);
                return;
            }

            $base = config('creem-agent.notifications.telegram_api_base', 'https://api.telegram.org');

            Http::post(rtrim($base, '/') . "/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $reply,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage failed: '.$e->getMessage());
        }
    }
}
