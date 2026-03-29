<?php

namespace Romansh\LaravelCreemAgent\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class HeartbeatAlert extends Notification
{
    public function __construct(
        public readonly string $store,
        public readonly array $change,
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
        $emoji = match ($this->change['severity'] ?? 'info') {
            'good_news' => '💰',
            'warning' => '⚠️',
            'alert' => '🚨',
            default => 'ℹ️',
        };

        return (new SlackMessage)
            ->content("{$emoji} [{$this->store}] {$this->change['message']}");
    }

    public function toTelegramText(): string
    {
        $emoji = match ($this->change['severity'] ?? 'info') {
            'good_news' => '💰',
            'warning' => '⚠️',
            'alert' => '🚨',
            default => 'ℹ️',
        };

        return "{$emoji} [{$this->store}] {$this->change['message']}";
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'heartbeat_alert',
            'store' => $this->store,
            'change' => $this->change,
        ];
    }
}
