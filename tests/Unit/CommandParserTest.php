<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\CommandParser;

class CommandParserTest extends TestCase
{
    public function test_parses_switch_store()
    {
        $p = new CommandParser();
        $r = $p->parse('switch to store secondary');
        $this->assertEquals('switch_store', $r['intent']);
        $this->assertEquals('secondary', $r['store']);
    }

    public function test_parses_subscription_queries()
    {
        $p = new CommandParser();
        $this->assertEquals('query_subscriptions', $p->parse('how many active subscriptions?')['intent']);
        $this->assertEquals('query_subscriptions', $p->parse('subscriptions with status past_due')['intent']);
    }

    public function test_parses_payment_issues_and_status()
    {
        $p = new CommandParser();
        $this->assertEquals('query_subscriptions', $p->parse('any payment issues?')['intent']);
        $this->assertEquals('status', $p->parse('what is the status?')['intent']);
    }

    public function test_parses_create_checkout_and_cancel()
    {
        $p = new CommandParser();
        $this->assertEquals('create_checkout', $p->parse('create checkout for product prod_123')['intent']);
        $this->assertEquals('cancel_subscription', $p->parse('cancel subscription sub_456')['intent']);
    }
}
