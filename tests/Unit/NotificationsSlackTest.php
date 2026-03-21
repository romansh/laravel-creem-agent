<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Notifications\FirstHeartbeat;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatSummary;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatAlert;
use Illuminate\Support\Facades\Config;

class NotificationsSlackTest extends TestCase
{
    public function test_first_heartbeat_to_slack_returns_message()
    {
        if (! class_exists(\Illuminate\Notifications\Messages\SlackMessage::class)) {
            $this->markTestSkipped('SlackMessage not available in this environment');
        }

        config()->set('creem-agent.notifications.slack_webhook_url', 'https://hooks');

        $fb = new FirstHeartbeat('sX', ['customerCount' => 1, 'transactionCount' => 2, 'subscriptions' => ['active' => 1]]);
        $msg = $fb->toSlack(null);
        $this->assertInstanceOf(\Illuminate\Notifications\Messages\SlackMessage::class, $msg);
    }

    public function test_heartbeat_summary_and_alert_to_slack()
    {
        if (! class_exists(\Illuminate\Notifications\Messages\SlackMessage::class)) {
            $this->markTestSkipped('SlackMessage not available in this environment');
        }

        config()->set('creem-agent.notifications.slack_webhook_url', 'https://hooks');

        $hs = new HeartbeatSummary('sX', [['message' => 'm1'], ['message' => 'm2']]);
        $this->assertInstanceOf(\Illuminate\Notifications\Messages\SlackMessage::class, $hs->toSlack(null));

        $ha = new HeartbeatAlert('sX', ['message' => 'hey', 'severity' => 'warning']);
        $this->assertInstanceOf(\Illuminate\Notifications\Messages\SlackMessage::class, $ha->toSlack(null));
    }
}
