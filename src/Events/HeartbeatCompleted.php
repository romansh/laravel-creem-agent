<?php

namespace Romansh\LaravelCreemAgent\Events;

use Illuminate\Foundation\Events\Dispatchable;

class HeartbeatCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $store,
        public readonly array $state,
        public readonly array $changes,
    ) {}
}
