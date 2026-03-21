<?php

namespace Romansh\LaravelCreemAgent\Support;

class TelegramModeResolver
{
    public function mode(): string
    {
        $mode = strtolower((string) config(
            'creem-agent.telegram.mode',
            config('creem-agent.openclaw.enabled') ? 'openclaw' : 'laravel'
        ));

        return in_array($mode, ['laravel', 'openclaw'], true) ? $mode : 'laravel';
    }

    public function usesLaravelTransport(): bool
    {
        return $this->mode() === 'laravel';
    }

    public function usesOpenClawGateway(): bool
    {
        return $this->mode() === 'openclaw';
    }
}