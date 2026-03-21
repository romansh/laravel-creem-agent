<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\NativeCliDriver;

class NativeCliDriverMoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/creem_native_more_' . uniqid();
        mkdir($this->tmpDir, 0700, true);
        putenv('PATH=' . $this->tmpDir . PATH_SEPARATOR . getenv('PATH'));
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . '/creem');
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_execute_supports_boolean_and_positional_arguments()
    {
        $script = <<<'BASH'
#!/bin/bash
echo -n '['
sep=''
for a in "$@"; do
  printf '%s' "$sep"
  esc=$(printf '%s' "$a" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')
  printf '"%s"' "$esc"
  sep=','
done
echo ']'
BASH;

        file_put_contents($this->tmpDir . '/creem', $script);
        chmod($this->tmpDir . '/creem', 0755);

        $driver = new NativeCliDriver();
        $result = $driver->execute('products', 'list', ['verbose' => true, 'quiet' => false, 'limit' => 5, 'extra']);

        $this->assertContains('--verbose', $result);
        $this->assertNotContains('--quiet', $result);
        $this->assertContains('--limit', $result);
        $this->assertContains('5', $result);
        $this->assertContains('extra', $result);
    }

    public function test_execute_throws_on_process_failure()
    {
        file_put_contents($this->tmpDir . '/creem', "#!/bin/bash\necho 'boom' 1>&2\nexit 1\n");
        chmod($this->tmpDir . '/creem', 0755);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Creem CLI failed');

        (new NativeCliDriver())->execute('products', 'list');
    }
}
