<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\AgentManager;
use Illuminate\Support\Facades\Http;

class AgentConsoleCommandsTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Romansh\LaravelCreemAgent\CreemAgentServiceProvider::class];
    }

    public function test_agent_chat_command_outputs_response()
    {
        $fake = $this->createMock(AgentManager::class);
        $fake->method('handleMessage')->willReturn('OK');

        $this->app->instance(AgentManager::class, $fake);

        $this->artisan('creem-agent:chat', ['message' => 'status'])->expectsOutput('OK')->assertExitCode(0);
    }

    public function test_install_command_fails_without_api()
    {
        // Ensure config lacks creem profile to trigger failure
        config(['creem.profiles.default' => null]);

        $this->artisan('creem-agent:install')->assertExitCode(1);
    }

    public function test_install_command_succeeds_with_fake_api()
    {
        // Provide a fake profile and fake HTTP responses
        config(['creem.profiles.default' => ['api_key' => 'test', 'test_mode' => true]]);
        config(['creem.test_api_url' => 'https://api.test']);

        Http::fake([
            'https://api.test/*' => Http::response(['items' => []], 200),
        ]);

        $this->artisan('creem-agent:install')->assertExitCode(0);
    }

}
