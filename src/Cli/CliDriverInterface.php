<?php

namespace Romansh\LaravelCreemAgent\Cli;

interface CliDriverInterface
{
    public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array;
}
