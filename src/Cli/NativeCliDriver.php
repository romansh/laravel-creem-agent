<?php

namespace Romansh\LaravelCreemAgent\Cli;

use Symfony\Component\Process\Process;

class NativeCliDriver implements CliDriverInterface
{
    public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
    {
        $binary = getenv('CREEM_CLI_BINARY') ?: 'creem';
        $command = [$binary, $resource, $action, '--json'];

        foreach ($args as $key => $value) {
            if (is_int($key)) {
                $command[] = (string) $value;
            } elseif (is_bool($value)) {
                if ($value) {
                    $command[] = "--{$key}";
                }
            } else {
                $command[] = "--{$key}";
                $command[] = (string) $value;
            }
        }

        $process = $this->createProcess($command);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                "Creem CLI failed: {$process->getErrorOutput()}"
            );
        }

        $output = $process->getOutput();
        $decoded = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON from creem CLI: {$output}");
        }

        return $decoded;
    }

    protected function createProcess(array $command): Process
    {
        return new Process($command);
    }
}
