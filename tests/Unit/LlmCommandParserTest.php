<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentClassifierAgent;
use Romansh\LaravelCreemAgent\Agent\LlmCommandParser;
use Romansh\LaravelCreemAgent\CreemAgentServiceProvider;

class LlmCommandParserTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        $providers = [
            CreemAgentServiceProvider::class,
        ];

        if (class_exists(AiServiceProvider::class)) {
            array_unshift($providers, AiServiceProvider::class);
        }

        return $providers;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(AiServiceProvider::class) || ! class_exists('Laravel\\Ai\\Promptable')) {
            $this->markTestSkipped('laravel/ai is not installed in the current vendor directory.');
        }
    }

    public function test_it_parses_llm_json_response()
    {
        IntentClassifierAgent::fake([
            '{"intent":"query_customers","store":null,"status":null,"id":null,"product_id":null}',
        ]);

        $parser = new LlmCommandParser();

        $result = $parser->parse('how many customers do we have?');

        $this->assertSame('query_customers', $result['intent']);
        $this->assertNull($result['status']);
    }

    public function test_it_normalizes_payment_issue_status()
    {
        IntentClassifierAgent::fake([
            '{"intent":"query_subscriptions","store":null,"status":"past due","id":null,"product_id":null}',
        ]);

        $parser = new LlmCommandParser();

        $result = $parser->parse('show payment issues');

        $this->assertSame('query_subscriptions', $result['intent']);
        $this->assertSame('past_due', $result['status']);
    }
}