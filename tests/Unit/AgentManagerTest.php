<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\AgentManager;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class AgentManagerTest extends TestCase
{
    public function test_get_and_set_active_store_proxy()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['setActiveStore', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->expects($this->once())->method('setActiveStore')->with('s1');
        $cli->method('getActiveStore')->willReturn('s1');

        $m = new AgentManager($cli);
        $m->setActiveStore('s1');
        $this->assertEquals('s1', $m->getActiveStore());
    }

    public function test_handle_message_routes()
    {
        // create a subscriptions proxy that returns empty list
        $subs = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listByStatus'])
            ->getMock();

        $subs->method('listByStatus')->willReturn(['items' => []]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('subscriptions')->willReturn($subs);
        $cli->method('getActiveStore')->willReturn('default');

        $m = new AgentManager($cli);
        $res = $m->handleMessage('how many active subscriptions?');
        $this->assertStringContainsString('subscription', $res);
    }
}
