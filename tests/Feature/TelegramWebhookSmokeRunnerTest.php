<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Support\TelegramWebhookSmokeRunner;

class TelegramWebhookSmokeRunnerTest extends TestCase
{
    public function test_run_reports_success_for_matching_successful_send(): void
    {
        Http::fake([
            'http://telegram-smoke.local/*' => Http::response(['ok' => true], 200),
        ]);

        $summary = app(TelegramWebhookSmokeRunner::class)->run([
            'telegram_api_base' => 'http://telegram-smoke.local',
            'telegram_token' => 'tok',
            'telegram_chat_id' => 'chat-1',
            'incoming_chat_id' => 'chat-1',
            'incoming_text' => 'smoke ping',
            'reply_text' => 'smoke reply',
        ]);

        $this->assertTrue($summary['ok']);
        $this->assertSame(200, $summary['controller_status']);
        $this->assertSame('http://telegram-smoke.local/bottok/sendMessage', $summary['expected_send_url']);
        $this->assertSame(1, $summary['matched_request_count']);
        $this->assertSame(1, $summary['matched_response_count']);
        $this->assertSame([], $summary['failures']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://telegram-smoke.local/bottok/sendMessage'
                && $data['chat_id'] === 'chat-1'
                && $data['text'] === 'smoke reply';
        });
    }

    public function test_run_reports_failure_for_unsuccessful_telegram_response(): void
    {
        Http::fake([
            'http://telegram-smoke.local/*' => Http::response(['ok' => false], 500),
        ]);

        $summary = app(TelegramWebhookSmokeRunner::class)->run([
            'telegram_api_base' => 'http://telegram-smoke.local',
            'telegram_token' => 'tok',
            'telegram_chat_id' => 'chat-1',
            'incoming_chat_id' => 'chat-1',
            'incoming_text' => 'smoke ping',
            'reply_text' => 'smoke reply',
        ]);

        $this->assertFalse($summary['ok']);
        $this->assertSame(200, $summary['controller_status']);
        $this->assertSame(1, $summary['matched_request_count']);
        $this->assertSame(1, $summary['matched_response_count']);
        $this->assertSame(500, $summary['responses'][0]['status']);
    }
}