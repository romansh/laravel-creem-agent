<?php

namespace Romansh\LaravelCreemAgent\Workflows;

use Romansh\LaravelCreemAgent\Events\ChangeDetected;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class FailedPaymentRecovery
{
    public function handle(ChangeDetected $event): void
    {
        $change = $event->change;

        if (($change['type'] ?? '') !== 'subscription_warning') {
            return;
        }

        $data = $change['data'] ?? [];
        if (($data['to'] ?? '') !== 'past_due') {
            return;
        }

        $subId = $data['subscription_id'] ?? 'unknown';
        $message = "Payment failed for subscription {$subId}. Creem will auto-retry. Monitor for expiration.";

        Log::warning("[CreemAgent][FailedPaymentRecovery] {$message}");

        Notification::route('slack', config('creem-agent.notifications.slack_webhook_url'))
            ->notify(new WorkflowAlert($event->store, 'FailedPaymentRecovery', $message, $data));
    }
}
