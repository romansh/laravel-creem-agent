<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\AgentManager;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Agent\ParsesAgentMessages;
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

        $llm = new class implements ParsesAgentMessages {
            public function parse(string $message): array
            {
                return ['intent' => 'unknown', 'message' => $message];
            }
        };

        $m = new AgentManager($cli, null, $llm);
        $res = $m->handleMessage('how many active subscriptions?');
        $this->assertStringContainsString('subscription', $res);
    }

    public function test_telegram_uses_rule_parser_before_llm()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rule = new class implements ParsesAgentMessages {
            public function parse(string $message): array
            {
                return ['intent' => 'status', 'message' => $message];
            }
        };

        $llm = new class implements ParsesAgentMessages {
            public function parse(string $message): array
            {
                return ['intent' => 'query_transactions', 'message' => $message];
            }
        };

        $router = $this->createMock(IntentRouter::class);
        $router->expects($this->once())
            ->method('route')
            ->with(['intent' => 'status', 'message' => 'status'])
            ->willReturn('rule response');

        $manager = new AgentManager($cli, $rule, $llm, $router);

        $this->assertSame('rule response', $manager->handleMessage('status', ['source' => 'telegram']));
    }

    public function test_telegram_falls_back_to_llm_when_rule_parser_returns_unknown()
    {
        config()->set('creem-agent.llm.enabled', true);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rule = new class implements ParsesAgentMessages {
            public function parse(string $message): array
            {
                return ['intent' => 'unknown', 'message' => $message];
            }
        };

        $llm = new class implements ParsesAgentMessages {
            public function parse(string $message): array
            {
                return ['intent' => 'status', 'message' => $message];
            }
        };

        $router = $this->createMock(IntentRouter::class);
        $router->expects($this->once())
            ->method('route')
            ->with(['intent' => 'status', 'message' => 'how are things going?'])
            ->willReturn('llm response');

        $manager = new AgentManager($cli, $rule, $llm, $router);

        $this->assertSame('llm response', $manager->handleMessage('how are things going?', ['source' => 'telegram']));
    }
}
