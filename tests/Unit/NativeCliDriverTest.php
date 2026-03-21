<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\NativeCliDriver;

class NativeCliDriverTest extends TestCase
{
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/creem_test_' . uniqid();
        mkdir($this->tmpDir, 0700, true);

        $script = <<<'BASH'
    #!/bin/bash
    # Return JSON-encoded argv (without script name) using pure shell
    echo -n '['
    sep=''
    for a in "$@"; do
      printf '%s' "$sep"
      # escape backslashes and double quotes
      esc=$(printf '%s' "$a" | sed -e 's/\\/\\\\/g' -e 's/"/\\\"/g' -e ':a;N;s/\n/\\n/;ta')
      printf '"%s"' "$esc"
      sep=','
    done
    echo ']'
    BASH;

        file_put_contents($this->tmpDir . '/creem', $script);
        chmod($this->tmpDir . '/creem', 0755);

        // Prepend our temp dir to PATH so Process finds the script
        putenv('PATH=' . $this->tmpDir . PATH_SEPARATOR . getenv('PATH'));
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . '/creem');
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_execute_returns_decoded_json()
    {
        $d = new NativeCliDriver();

        $res = $d->execute('transactions', 'get', ['id' => 'tx_1']);

        $this->assertIsArray($res);
        $this->assertContains('transactions', $res);
        $this->assertContains('get', $res);
        $this->assertContains('--id', $res);
        $this->assertContains('tx_1', $res);
    }

    public function test_invalid_json_throws()
    {
        // Overwrite script to emit invalid JSON
        file_put_contents($this->tmpDir . '/creem', "#!/bin/bash\necho 'not-json'\n");
        chmod($this->tmpDir . '/creem', 0755);

        $this->expectException(\RuntimeException::class);

        $d = new NativeCliDriver();
        $d->execute('products', 'list', []);
    }
}
