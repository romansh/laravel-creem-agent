<?php

namespace Romansh\LaravelCreemAgent\Workflows;

use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;
use Illuminate\Support\Facades\Notification;

class NewCustomerWelcome
{
    public function handle(HeartbeatCompleted $event): void
    {
        $changes = $event->changes;
        $newCustomers = array_filter($changes, fn($c) => ($c['type'] ?? '') === 'new_customers');

        if (empty($newCustomers)) {
            return;
        }

        $total = 0;
        foreach ($newCustomers as $c) {
            $total += $c['data']['count'] ?? 0;
        }

        if ($total > 0) {
            $message = "{$total} new customer(s) joined your store!";

            Notification::route('slack', config('creem-agent.notifications.slack_webhook_url'))
                ->notify(new WorkflowAlert($event->store, 'NewCustomerWelcome', $message, [
                    'new_count' => $total,
                ]));
        }
    }
}
