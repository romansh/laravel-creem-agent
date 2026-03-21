<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class IntentRouterMoreTest extends TestCase
{
    public function test_help_returns_usage_list()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'help']);

        $this->assertStringContainsString("I can help you with", $res);
        $this->assertStringContainsString("create checkout", $res);
    }

    public function test_create_checkout_success_and_failure()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('getActiveStore')->willReturn('default');

        // success
        $cli->method('execute')->willReturn(['checkout_url' => 'https://pay.example/ok']);
        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'create_checkout', 'product_id' => 'prod_1']);
        $this->assertStringContainsString('Checkout created: https://pay.example/ok', $res);

        // failure
        $cli2 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli2->method('getActiveStore')->willReturn('default');
        $cli2->method('execute')->will($this->throwException(new \Exception('boom')));

        $router2 = new IntentRouter($cli2);
        $res2 = $router2->route(['intent' => 'create_checkout', 'product_id' => 'prod_1']);
        $this->assertStringContainsString('Failed to create checkout', $res2);

        $cli3 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli3->method('getActiveStore')->willReturn('default');
        $cli3->method('execute')->willReturn([]);

        $router3 = new IntentRouter($cli3);
        $res3 = $router3->route(['intent' => 'create_checkout', 'product_id' => 'prod_1']);
        $this->assertStringContainsString('Checkout created: N/A', $res3);
    }

    public function test_cancel_subscription_success_and_failure()
    {
        $subs = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cancel'])
            ->getMock();

        $subs->expects($this->once())->method('cancel')->with('sub_1', true);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('subscriptions')->willReturn($subs);
        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'cancel_subscription', 'id' => 'sub_1']);
        $this->assertStringContainsString('scheduled for cancellation', $res);

        // failure
        $subs2 = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cancel'])
            ->getMock();

        $subs2->method('cancel')->will($this->throwException(new \Exception('nope')));

        $cli2 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli2->method('subscriptions')->willReturn($subs2);
        $cli2->method('getActiveStore')->willReturn('default');

        $router2 = new IntentRouter($cli2);
        $res2 = $router2->route(['intent' => 'cancel_subscription', 'id' => 'sub_1']);
        $this->assertStringContainsString('Failed to cancel', $res2);
    }
}
