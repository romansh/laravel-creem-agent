<?php

namespace Romansh\LaravelCreemAgent\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class HeartbeatSummary extends Notification
{
    public function __construct(
        public readonly string $store,
        public readonly array $changes,
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if (config('creem-agent.notifications.slack_webhook_url')) {
            $channels[] = 'slack';
        }
        return $channels;
    }

    public function toSlack($notifiable): SlackMessage
    {
        $lines = array_map(fn($c) => "• {$c['message']}", $this->changes);
        $summary = implode("\n", $lines);

        return (new SlackMessage)
            ->content("📊 Creem store update ({$this->store}):\n{$summary}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'heartbeat_summary',
            'store' => $this->store,
            'changes' => $this->changes,
            'count' => count($this->changes),
        ];
    }
}
