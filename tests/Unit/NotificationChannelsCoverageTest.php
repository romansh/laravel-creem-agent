<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatAlert;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatSummary;

class NotificationChannelsCoverageTest extends TestCase
{
    public function test_alert_and_summary_via_without_slack()
    {
        config()->set('creem-agent.notifications.slack_webhook_url', null);

        $alert = new HeartbeatAlert('s1', ['message' => 'm']);
        $summary = new HeartbeatSummary('s1', [['message' => 'm1']]);

        $this->assertSame(['database'], $alert->via(null));
        $this->assertSame(['database'], $summary->via(null));
    }

    public function test_alert_and_summary_via_with_slack()
    {
        config()->set('creem-agent.notifications.slack_webhook_url', 'https://hooks.example.test');

        $alert = new HeartbeatAlert('s1', ['message' => 'm', 'severity' => 'info']);
        $summary = new HeartbeatSummary('s1', [['message' => 'm1']]);

        $this->assertSame(['database', 'slack'], $alert->via(null));
        $this->assertSame(['database', 'slack'], $summary->via(null));
    }
}
