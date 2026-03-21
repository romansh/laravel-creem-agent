<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Http\Controllers\TelegramWebhookController;

class TelegramWebhookControllerFailureTest extends TestCase
{
    public function test_handle_tolerates_agent_and_telegram_failures()
    {
        config()->set('creem-agent.notifications.telegram_bot_token', 'tok');
        config()->set('creem-agent.notifications.telegram_chat_id', '123');

        $controller = new TelegramWebhookController(
            function (): string {
                throw new \RuntimeException('agent failure');
            },
            function (): void {
                throw new \RuntimeException('telegram failure');
            }
        );
        $response = $controller->handle(Request::create('/', 'POST', [
            'message' => ['text' => 'hi', 'chat' => ['id' => '123']],
        ]));

        $this->assertSame(200, $response->getStatusCode());
    }
}
