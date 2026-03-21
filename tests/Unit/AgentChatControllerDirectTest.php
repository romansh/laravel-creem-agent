<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\AgentManager;
use Romansh\LaravelCreemAgent\Http\Controllers\AgentChatController;

class AgentChatControllerDirectTest extends TestCase
{
    public function test_handle_requires_message_without_http_kernel()
    {
        $agent = $this->createMock(AgentManager::class);

        $response = (new AgentChatController())->handle(Request::create('/', 'POST', []), $agent);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['error' => 'Message is required'], $response->getData(true));
    }

    public function test_handle_returns_agent_payload_without_http_kernel()
    {
        $agent = $this->createMock(AgentManager::class);
        $agent->method('handleMessage')->with('status')->willReturn('OK');
        $agent->method('getActiveStore')->willReturn('default');

        $response = (new AgentChatController())->handle(Request::create('/', 'POST', ['message' => 'status']), $agent);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['response' => 'OK', 'store' => 'default'], $response->getData(true));
    }
}