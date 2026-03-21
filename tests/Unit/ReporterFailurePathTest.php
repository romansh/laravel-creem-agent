<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Carbon\CarbonImmutable;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Heartbeat\Reporter;

class ReporterFailurePathTest extends TestCase
{
    public function test_reporter_swallow_notification_send_failures()
    {
        $reporter = new Reporter(
            fn() => new CarbonImmutable('2026-03-19 12:00:00'),
            function (): void {
                throw new \RuntimeException('notify failed');
            }
        );

        $reporter->reportChanges('default', [[
            'severity' => 'info',
            'message' => 'x',
            'type' => 't',
        ]]);

        $this->addToAssertionCount(1);
    }
}
