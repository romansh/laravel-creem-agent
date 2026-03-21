<?php

namespace Romansh\LaravelCreemAgent\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkflowTriggered
{
    use Dispatchable;

    public function __construct(
        public readonly string $store,
        public readonly string $workflow,
        public readonly array $data,
    ) {}
}
