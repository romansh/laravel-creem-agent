<?php

namespace Romansh\LaravelCreemAgent\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ChangeDetected
{
    use Dispatchable;

    public function __construct(
        public readonly string $store,
        public readonly array $change,
    ) {}
}
