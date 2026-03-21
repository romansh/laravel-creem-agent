<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\CommandParser;

class CommandParserMoreTest extends TestCase
{
    public function test_parses_customers_transactions_products_help_and_unknown()
    {
        $parser = new CommandParser();

        $this->assertSame('query_customers', $parser->parse('count customers')['intent']);
        $this->assertSame('query_transactions', $parser->parse('show recent sales')['intent']);
        $this->assertSame('run_heartbeat', $parser->parse('run heartbeat now')['intent']);
        $this->assertSame('query_products', $parser->parse('products')['intent']);
        $this->assertSame('help', $parser->parse('help me')['intent']);

        $unknown = $parser->parse('completely unrelated phrase');
        $this->assertSame('unknown', $unknown['intent']);
        $this->assertSame('completely unrelated phrase', $unknown['message']);
    }
}
