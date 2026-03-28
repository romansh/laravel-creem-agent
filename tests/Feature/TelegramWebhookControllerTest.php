<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Romansh\LaravelCreemAgent\Http\Controllers\TelegramWebhookController;

class TelegramWebhookControllerTest extends TestCase
{
    public function test_handle_forwards_and_sends_reply()
    {
        config()->set('creem-agent.notifications.telegram_bot_token', 'tok');
        config()->set('creem-agent.notifications.telegram_chat_id', '123');

        $sent = [];

        $controller = new TelegramWebhookController(
            fn(string $text, mixed $chatId) => json_encode(['reply' => 'hello from agent']),
            function (string $token, mixed $chatId, string $reply) use (&$sent): void {
                $sent[] = compact('token', 'chatId', 'reply');
            }
        );

        $req = Request::create('/', 'POST', ['message' => ['text' => 'hi', 'chat' => ['id' => '123']]]);
        $res = $controller->handle($req);

        $this->assertEquals(200, $res->getStatusCode());
        $this->assertSame([['token' => 'tok', 'chatId' => '123', 'reply' => 'hello from agent']], $sent);
    }

    public function test_handle_uses_default_internal_forward_and_http_send_paths()
    {
        Route::post('/creem-agent/chat', fn() => response()->json(['reply' => 'route reply']));
        config()->set('creem-agent.notifications.telegram_bot_token', 'tok');
        config()->set('creem-agent.notifications.telegram_chat_id', 'fallback');
        config()->set('creem-agent.notifications.telegram_api_base', 'http://telegram-smoke.local');

        Http::fake();

        $controller = new TelegramWebhookController();
        $response = $controller->handle(Request::create('/', 'POST', [
            'message' => ['text' => 'hi'],
            'chat' => ['id' => 'chat-1'],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'http://telegram-smoke.local/bottok/sendMessage'
                && $data['chat_id'] === 'chat-1'
                && $data['text'] === 'route reply';
        });
    }
}
