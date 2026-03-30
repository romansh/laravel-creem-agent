<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Romansh\LaravelCreemAgent\Heartbeat\Reporter;
use Romansh\LaravelCreemAgent\Notifications\FirstHeartbeat;

class ReporterTest extends TestCase
{
    public function test_report_first_run_sends_notification()
    {
        Notification::fake();
        Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true], 200)]);

        config([
            'creem-agent.notifications.telegram_bot_token' => 'bot-token',
            'creem-agent.notifications.telegram_chat_id' => '12345',
            'creem-agent.notifications.telegram_api_base' => 'https://api.telegram.test',
        ]);

        $rep = new Reporter();
        $rep->reportFirstRun('default', ['some' => 'state']);
        Notification::assertSentOnDemand(FirstHeartbeat::class);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.telegram.test/botbot-token/sendMessage'
                && $request['chat_id'] === '12345';
        });
    }

    public function test_report_changes_suppresses_on_empty()
    {
        Notification::fake();
        $rep = new Reporter();
        $rep->reportChanges('default', []);
        Notification::assertNothingSent();
    }

    public function test_report_first_run_skips_telegram_route_when_openclaw_owns_telegram()
    {
        Notification::fake();
        Http::fake();

        config(['creem-agent.telegram.mode' => 'openclaw']);
        config([
            'creem-agent.notifications.telegram_bot_token' => 'bot-token',
            'creem-agent.notifications.telegram_chat_id' => '12345',
            'creem-agent.notifications.telegram_api_base' => 'https://api.telegram.test',
        ]);

        $rep = new Reporter();
        $rep->reportFirstRun('default', ['some' => 'state']);

        Notification::assertSentOnDemand(FirstHeartbeat::class, function ($notification, array $channels, $notifiable): bool {
            return !array_key_exists('telegram', $notifiable->routes);
        });
        Http::assertNothingSent();
    }

    public function test_report_first_run_sends_telegram_when_forced_from_console(): void
    {
        Notification::fake();
        Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true], 200)]);

        config(['creem-agent.telegram.mode' => 'openclaw']);
        config([
            'creem-agent.notifications.telegram_bot_token' => 'bot-token',
            'creem-agent.notifications.telegram_chat_id' => '12345',
            'creem-agent.notifications.telegram_api_base' => 'https://api.telegram.test',
        ]);

        $rep = new Reporter(forceTelegramDirect: true);
        $rep->reportFirstRun('default', ['some' => 'state']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.telegram.test/botbot-token/sendMessage'
                && $request['chat_id'] === '12345';
        });
    }
}
