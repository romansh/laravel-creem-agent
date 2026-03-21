<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Notification;
use Romansh\LaravelCreemAgent\Heartbeat\Reporter;
use Romansh\LaravelCreemAgent\Notifications\FirstHeartbeat;

class ReporterTest extends TestCase
{
    public function test_report_first_run_sends_notification()
    {
        Notification::fake();

        config(['creem-agent.notifications.telegram_chat_id' => '12345']);

        $rep = new Reporter();
        $rep->reportFirstRun('default', ['some' => 'state']);
        Notification::assertSentOnDemand(FirstHeartbeat::class);
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

        config(['creem-agent.telegram.mode' => 'openclaw']);
        config(['creem-agent.notifications.telegram_chat_id' => '12345']);

        $rep = new Reporter();
        $rep->reportFirstRun('default', ['some' => 'state']);

        Notification::assertSentOnDemand(FirstHeartbeat::class, function ($notification, array $channels, $notifiable): bool {
            return !array_key_exists('telegram', $notifiable->routes);
        });
    }
}
