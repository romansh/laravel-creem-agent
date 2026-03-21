<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Carbon\CarbonImmutable;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Heartbeat\Reporter;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatAlert;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatSummary;

class ReporterMoreTest extends TestCase
{
    public function test_report_changes_sends_alert_and_summary_outside_quiet_hours()
    {
        $sent = [];
        config()->set('creem-agent.quiet_hours.start', 23);
        config()->set('creem-agent.quiet_hours.end', 7);

        $reporter = new Reporter(
            fn() => new CarbonImmutable('2026-03-19 12:00:00'),
            function (string $store, object $notification) use (&$sent): void {
                $sent[] = [$store, $notification::class];
            }
        );

        $reporter->reportChanges('default', [['severity' => 'info', 'message' => 'one', 'type' => 'x']]);
        $reporter->reportChanges('default', [
            ['severity' => 'info', 'message' => 'one', 'type' => 'x'],
            ['severity' => 'warning', 'message' => 'two', 'type' => 'y'],
        ]);

        $this->assertSame([
            ['default', HeartbeatAlert::class],
            ['default', HeartbeatSummary::class],
        ], $sent);
    }

    public function test_reporter_suppresses_non_critical_in_quiet_hours_but_allows_critical()
    {
        $sent = [];
        config()->set('creem-agent.quiet_hours.start', 23);
        config()->set('creem-agent.quiet_hours.end', 7);

        $reporter = new Reporter(
            fn() => new CarbonImmutable('2026-03-19 01:00:00'),
            function (string $store, object $notification) use (&$sent): void {
                $sent[] = $notification::class;
            }
        );

        $reporter->reportFirstRun('default', ['customerCount' => 1]);
        $reporter->reportChanges('default', [['severity' => 'warning', 'message' => 'critical', 'type' => 'x']]);

        $this->assertSame([HeartbeatAlert::class], $sent);
    }

    public function test_reporter_respects_non_wrapping_quiet_hours_window()
    {
        $sent = [];
        config()->set('creem-agent.quiet_hours.start', 9);
        config()->set('creem-agent.quiet_hours.end', 17);

        $reporter = new Reporter(
            fn() => new CarbonImmutable('2026-03-19 10:00:00'),
            function (string $store, object $notification) use (&$sent): void {
                $sent[] = $notification::class;
            }
        );

        $reporter->reportChanges('default', [['severity' => 'info', 'message' => 'inside quiet window', 'type' => 'x']]);

        $this->assertSame([], $sent);
    }
}
