<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;

class AgentStatusControllerTest extends TestCase
{
    public function test_index_returns_json_status()
    {
        $tmp = sys_get_temp_dir() . '/creem_state_ctrl_' . uniqid();
        config()->set('creem-agent.state_path', $tmp);
        config()->set('creem-agent.stores', ['s1' => ['profile' => 's1']]);

        $stateMgr = new \Romansh\LaravelCreemAgent\Heartbeat\StateManager();
        $stateMgr->save('s1', [
            'lastCheckAt' => '2020-01-01T00:00:00Z',
            'transactionCount' => 3,
            'customerCount' => 4,
            'subscriptions' => ['active' => 1],
        ]);

        $cli = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\CreemCliManager::class)
            ->onlyMethods(['isNativeCliAvailable', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('isNativeCliAvailable')->willReturn(false);
        $cli->method('getActiveStore')->willReturn('s1');

        $c = new \Romansh\LaravelCreemAgent\Http\Controllers\AgentStatusController();
        $resp = $c->index($cli);

        $this->assertEquals(200, $resp->getStatusCode());
        $data = $resp->getData(true);
        $this->assertEquals('artisan', $data['cli_backend']);
        $this->assertEquals('s1', $data['active_store']);
        $this->assertArrayHasKey('s1', $data['stores']);
    }
}
