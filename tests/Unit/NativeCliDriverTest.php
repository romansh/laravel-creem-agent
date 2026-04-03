<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\NativeCliDriver;
use Symfony\Component\Process\Process;

class NativeCliDriverTest extends TestCase
{
    public function test_execute_returns_decoded_json()
    {
        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setTimeout', 'run', 'isSuccessful', 'getOutput'])
            ->getMock();

        $process->expects($this->once())->method('setTimeout')->with(30)->willReturnSelf();
        $process->expects($this->once())->method('run');
        $process->expects($this->once())->method('isSuccessful')->willReturn(true);
        $process->expects($this->once())->method('getOutput')->willReturn('["transactions","get","--json","--id","tx_1"]');

        $d = new class($process) extends NativeCliDriver {
            public array $capturedCommand = [];

            public function __construct(private Process $process) {}

            protected function createProcess(array $command): Process
            {
                $this->capturedCommand = $command;
                return $this->process;
            }
        };

        $res = $d->execute('transactions', 'get', ['id' => 'tx_1']);

        $this->assertIsArray($res);
        $this->assertContains('transactions', $res);
        $this->assertContains('get', $res);
        $this->assertContains('--id', $res);
        $this->assertContains('tx_1', $res);
        $this->assertSame(['creem', 'transactions', 'get', '--json', '--id', 'tx_1'], $d->capturedCommand);
    }

    public function test_invalid_json_throws()
    {
        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setTimeout', 'run', 'isSuccessful', 'getOutput'])
            ->getMock();

        $process->method('setTimeout')->willReturnSelf();
        $process->method('run');
        $process->method('isSuccessful')->willReturn(true);
        $process->method('getOutput')->willReturn('not-json');

        $this->expectException(\RuntimeException::class);

        $d = new class($process) extends NativeCliDriver {
            public function __construct(private Process $process) {}

            protected function createProcess(array $command): Process
            {
                return $this->process;
            }
        };

        $d->execute('products', 'list', []);
    }
}
