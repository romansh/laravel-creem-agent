<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Romansh\LaravelCreemAgent\Agent\AgentManager;
use Orchestra\Testbench\TestCase;

class AgentChatControllerTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Romansh\LaravelCreemAgent\CreemAgentServiceProvider::class];
    }

    public function test_chat_endpoint_requires_message()
    {
        $response = $this->postJson('/creem-agent/chat', []);
        $response->assertStatus(422)->assertJson(['error' => 'Message is required']);
    }

    public function test_chat_endpoint_returns_agent_response()
    {
        // Bind a fake AgentManager to the container
        $fake = $this->createMock(AgentManager::class);
        $fake->method('handleMessage')->willReturn('OK');
        $fake->method('getActiveStore')->willReturn('default');

        $this->app->instance(AgentManager::class, $fake);

        $response = $this->postJson('/creem-agent/chat', ['message' => 'status']);
        $response->assertStatus(200)->assertJson(['response' => 'OK', 'store' => 'default']);
    }
}
