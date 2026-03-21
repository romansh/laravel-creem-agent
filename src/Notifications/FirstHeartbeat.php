<?php

namespace Romansh\LaravelCreemAgent\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class FirstHeartbeat extends Notification
{
    public function __construct(
        public readonly string $store,
        public readonly array $state,
    ) {}

    public function via($notifiable): array
    {
        return $this->resolveChannels();
    }

    public function toSlack($notifiable): SlackMessage
    {
        $s = $this->state['subscriptions'] ?? [];

        return (new SlackMessage)
            ->success()
            ->content("🔗 Creem store monitoring active ({$this->store})")
            ->attachment(function ($attachment) use ($s) {
                $attachment->fields([
                    'Customers' => (string) ($this->state['customerCount'] ?? 0),
                    'Active subs' => (string) ($s['active'] ?? 0),
                    'Trialing' => (string) ($s['trialing'] ?? 0),
                    'Past due' => ($s['past_due'] ?? 0) > 0 ? "⚠️ {$s['past_due']}" : '0',
                    'Transactions' => (string) ($this->state['transactionCount'] ?? 0),
                ]);
            });
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'first_heartbeat',
            'store' => $this->store,
            'state' => $this->state,
        ];
    }

    private function resolveChannels(): array
    {
        $channels = ['database'];
        if (config('creem-agent.notifications.slack_webhook_url')) {
            $channels[] = 'slack';
        }
        return $channels;
    }
}
