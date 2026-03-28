<?php

namespace Romansh\LaravelCreemAgent\Support;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Romansh\LaravelCreemAgent\Http\Controllers\TelegramWebhookController;

class TelegramWebhookSmokeRunner
{
    public function run(array $options = []): array
    {
        $telegramBase = $options['telegram_api_base'] ?? 'http://127.0.0.1:18081/anything';
        $telegramToken = $options['telegram_token'] ?? 'tok';
        $telegramChatId = $options['telegram_chat_id'] ?? 'chat-1';
        $incomingChatId = $options['incoming_chat_id'] ?? $telegramChatId;
        $incomingText = $options['incoming_text'] ?? 'smoke ping';
        $replyText = $options['reply_text'] ?? 'smoke reply';
        $forwarder = $options['forwarder'] ?? static fn(string $text, mixed $chatId): string => json_encode(['reply' => $replyText]);

        $requests = [];
        $responses = [];
        $failures = [];

        app('events')->listen(RequestSending::class, static function (RequestSending $event) use (&$requests): void {
            $requests[] = [
                'method' => $event->request->method(),
                'url' => $event->request->url(),
                'data' => $event->request->data(),
            ];
        });

        app('events')->listen(ResponseReceived::class, static function (ResponseReceived $event) use (&$responses): void {
            $responses[] = [
                'url' => $event->request->url(),
                'status' => $event->response->status(),
                'body' => $event->response->body(),
            ];
        });

        app('events')->listen(ConnectionFailed::class, static function (ConnectionFailed $event) use (&$failures): void {
            $failures[] = [
                'url' => $event->request->url(),
                'error' => $event->exception->getMessage(),
            ];
        });

        config()->set('creem-agent.notifications.telegram_bot_token', $telegramToken);
        config()->set('creem-agent.notifications.telegram_chat_id', $telegramChatId);
        config()->set('creem-agent.notifications.telegram_api_base', $telegramBase);

        $controller = new TelegramWebhookController($forwarder);
        $response = $controller->handle(Request::create('/creem-agent/telegram/webhook', 'POST', [
            'message' => [
                'text' => $incomingText,
                'chat' => ['id' => $incomingChatId],
            ],
        ]));

        $expectedUrl = rtrim($telegramBase, '/')."/bot{$telegramToken}/sendMessage";
        $matchingRequests = array_values(array_filter($requests, static fn(array $request): bool => $request['url'] === $expectedUrl));
        $matchingResponses = array_values(array_filter($responses, static fn(array $item): bool => $item['url'] === $expectedUrl));
        $hasSuccessfulResponse = $matchingResponses !== []
            && array_reduce($matchingResponses, static fn(bool $carry, array $item): bool => $carry && $item['status'] >= 200 && $item['status'] < 300, true);

        return [
            'ok' => $response->getStatusCode() === 200 && $matchingRequests !== [] && $failures === [] && $hasSuccessfulResponse,
            'controller_status' => $response->getStatusCode(),
            'expected_send_url' => $expectedUrl,
            'matched_request_count' => count($matchingRequests),
            'matched_response_count' => count($matchingResponses),
            'requests' => $matchingRequests,
            'responses' => $matchingResponses,
            'failures' => $failures,
        ];
    }
}