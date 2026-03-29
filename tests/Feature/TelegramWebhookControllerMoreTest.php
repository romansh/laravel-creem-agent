<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Http\Controllers\TelegramWebhookController;

class TelegramWebhookControllerMoreTest extends TestCase
{
    public function test_handle_returns_bad_request_when_message_missing()
    {
        $controller = new TelegramWebhookController();
        $response = $controller->handle(Request::create('/', 'POST', []));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_handle_uses_callback_query_default_chat_and_message_fallbacks()
    {
        config()->set('creem-agent.notifications.telegram_bot_token', 'tok');
        config()->set('creem-agent.notifications.telegram_chat_id', '999');

        $sent = [];
        $call = 0;

        $controller = new TelegramWebhookController(
            function () use (&$call) {
                $call++;

                return $call === 1
                    ? json_encode(['message' => 'agent message'])
                    : json_encode(['unexpected' => 'payload']);
            },
            function (string $token, mixed $chatId, string $reply) use (&$sent): void {
                $sent[] = compact('token', 'chatId', 'reply');
            }
        );

        $controller->handle(Request::create('/', 'POST', [
            'callback_query' => ['data' => 'ping'],
        ]));

        $controller->handle(Request::create('/', 'POST', [
            'edited_message' => ['text' => 'edit'],
        ]));

        $this->assertSame([
            ['token' => 'tok', 'chatId' => '999', 'reply' => 'agent message'],
            ['token' => 'tok', 'chatId' => '999', 'reply' => '{"unexpected":"payload"}'],
        ], $sent);
    }

    public function test_handle_extracts_response_key_from_agent_json()
    {
        config()->set('creem-agent.notifications.telegram_bot_token', 'tok');
        config()->set('creem-agent.notifications.telegram_chat_id', '999');

        $sent = [];

        $controller = new TelegramWebhookController(
            fn() => json_encode(['response' => "Store 'default' status:\n  Active subs: 13", 'store' => 'default']),
            function (string $token, mixed $chatId, string $reply) use (&$sent): void {
                $sent[] = compact('token', 'chatId', 'reply');
            }
        );

        $response = $controller->handle(Request::create('/', 'POST', [
            'message' => ['text' => 'status', 'chat' => ['id' => '999']],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            ['token' => 'tok', 'chatId' => '999', 'reply' => "Store 'default' status:\n  Active subs: 13"],
        ], $sent);
    }

    public function test_handle_uses_channel_post_and_skips_send_when_chat_or_token_missing()
    {
        $sent = [];

        $controller = new TelegramWebhookController(
            fn() => 'plain reply',
            function (string $token, mixed $chatId, string $reply) use (&$sent): void {
                $sent[] = compact('token', 'chatId', 'reply');
            }
        );

        $response = $controller->handle(Request::create('/', 'POST', [
            'channel_post' => ['text' => 'channel ping'],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $sent);
    }

    public function test_handle_rejects_message_exceeding_max_length()
    {
        $controller = new TelegramWebhookController();
        $response = $controller->handle(Request::create('/', 'POST', [
            'message' => ['text' => str_repeat('a', 2001), 'chat' => ['id' => '1']],
        ]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['ok']);
        $this->assertSame('message too long', $data['reason']);
    }
}
