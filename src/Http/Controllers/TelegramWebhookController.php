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

        if (mb_strlen($text) > 2000) {
            return response()->json(['ok' => false, 'reason' => 'message too long'], 400);
        }

        // Provide a simple menu for common standard messages to avoid LLM usage
        $lower = mb_strtolower(trim($text));
        if (in_array($lower, ['menu', '/menu'], true)) {
            $keyboard = [
                'keyboard' => [[['text' => 'status']], [['text' => 'recent transactions']], [['text' => 'any payment issues?']]],
                'one_time_keyboard' => true,
                'resize_keyboard' => true,
            ];

            $this->sendReply($token, $chatId, ['text' => 'Choose an option:', 'reply_markup' => $keyboard]);

            return response()->json(['ok' => true]);
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

        if (!empty($json['response'])) {
            return $json['response'];
        }

        if (!empty($json['message'])) {
            return $json['message'];
        }

        return (string) json_encode($json);
    }

    private function sendReply(?string $token, mixed $chatId, mixed $reply): void
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

            // Allow passing a payload array to include reply_markup and other options
            if (is_array($reply)) {
                $payload = array_merge(['chat_id' => $chatId], $reply);
            } else {
                $payload = ['chat_id' => $chatId, 'text' => $reply];
            }

            $response = Http::post(rtrim($base, '/') . "/bot{$token}/sendMessage", $payload);

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
