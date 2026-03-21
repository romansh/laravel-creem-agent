<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Heartbeat\SubscriptionChecker;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class SubscriptionCheckerTest extends TestCase
{
    public function test_counts_and_transitions()
    {
        $prev = [
            'subscriptions' => [],
            'knownSubscriptions' => ['sub_old' => 'active'],
        ];

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions'])
            ->disableOriginalConstructor()
            ->getMock();

        $subProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->getMock();

        // For 'active' return a new subscription, for others return empty
        $subProxy->method('listByStatus')->willReturnCallback(function ($status) {
            if ($status === 'active') {
                return ['items' => [ ['id' => 'sub_new'] ]];
            }
            return [];
        });

        $cli->method('subscriptions')->willReturn($subProxy);

        $checker = new SubscriptionChecker($cli);
        $res = $checker->check($prev);

        $this->assertArrayHasKey('counts', $res);
        $this->assertArrayHasKey('knownSubscriptions', $res);
        $this->assertArrayHasKey('transitions', $res);
        $this->assertNotEmpty($res['transitions']);
    }
}
