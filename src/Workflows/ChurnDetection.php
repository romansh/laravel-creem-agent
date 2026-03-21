<?php

namespace Romansh\LaravelCreemAgent\Workflows;

use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class ChurnDetection
{
    public function handle(HeartbeatCompleted $event): void
    {
        $changes = $event->changes;
        $cancellations = array_filter($changes, function ($c) {
            return in_array($c['type'] ?? '', ['subscription_alert']) &&
                   in_array($c['data']['to'] ?? '', ['canceled', 'scheduled_cancel']);
        });

        if (count($cancellations) >= 2) {
            $count = count($cancellations);
            $message = "{$count} cancellations detected in this heartbeat cycle. Possible churn trend.";

            Log::warning("[CreemAgent][ChurnDetection] {$message}");

            Notification::route('slack', config('creem-agent.notifications.slack_webhook_url'))
                ->notify(new WorkflowAlert($event->store, 'ChurnDetection', $message, [
                    'cancellation_count' => $count,
                ]));
        }
    }
}
