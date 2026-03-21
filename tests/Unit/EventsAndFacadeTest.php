<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Events\WorkflowTriggered;
use Romansh\LaravelCreemAgent\Facades\CreemCli;

class EventsAndFacadeTest extends TestCase
{
    public function test_workflow_triggered_event_properties()
    {
        $e = new WorkflowTriggered('s1', 'wf1', ['a' => 1]);
        $this->assertEquals('s1', $e->store);
        $this->assertEquals('wf1', $e->workflow);
        $this->assertEquals(['a' => 1], $e->data);
    }

    public function test_creem_cli_facade_accessor()
    {
        $ref = new \ReflectionMethod(CreemCli::class, 'getFacadeAccessor');
        $ref->setAccessible(true);
        $acc = $ref->invoke(null);
        $this->assertEquals('creem-cli', $acc);
    }
}
