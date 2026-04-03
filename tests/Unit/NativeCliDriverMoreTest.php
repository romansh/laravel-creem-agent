<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\NativeCliDriver;
use Symfony\Component\Process\Process;

class NativeCliDriverMoreTest extends TestCase
{
    public function test_execute_supports_boolean_and_positional_arguments()
    {
        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setTimeout', 'run', 'isSuccessful', 'getOutput'])
            ->getMock();

        $process->expects($this->once())->method('setTimeout')->with(30)->willReturnSelf();
        $process->expects($this->once())->method('run');
        $process->expects($this->once())->method('isSuccessful')->willReturn(true);
        $process->expects($this->once())->method('getOutput')->willReturn('["products","list","--json","--verbose","--limit","5","extra"]');

        $driver = new class($process) extends NativeCliDriver {
            public array $capturedCommand = [];

            public function __construct(private Process $process) {}

            protected function createProcess(array $command): Process
            {
                $this->capturedCommand = $command;
                return $this->process;
            }
        };

        $result = $driver->execute('products', 'list', ['verbose' => true, 'quiet' => false, 'limit' => 5, 'extra']);

        $this->assertContains('--verbose', $result);
        $this->assertNotContains('--quiet', $result);
        $this->assertContains('--limit', $result);
        $this->assertContains('5', $result);
        $this->assertContains('extra', $result);
        $this->assertSame(['creem', 'products', 'list', '--json', '--verbose', '--limit', '5', 'extra'], $driver->capturedCommand);
    }

    public function test_execute_throws_on_process_failure()
    {
        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setTimeout', 'run', 'isSuccessful', 'getErrorOutput'])
            ->getMock();

        $process->method('setTimeout')->willReturnSelf();
        $process->method('run');
        $process->method('isSuccessful')->willReturn(false);
        $process->method('getErrorOutput')->willReturn('boom');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Creem CLI failed');

        $driver = new class($process) extends NativeCliDriver {
            public function __construct(private Process $process) {}

            protected function createProcess(array $command): Process
            {
                return $this->process;
            }
        };

        $driver->execute('products', 'list');
    }
}
