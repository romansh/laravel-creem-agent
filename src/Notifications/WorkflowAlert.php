<?php

namespace Romansh\LaravelCreemAgent\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class WorkflowAlert extends Notification
{
    public function __construct(
        public readonly string $store,
        public readonly string $workflow,
        public readonly string $message,
        public readonly array $data = [],
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
        return (new SlackMessage)
            ->warning()
            ->content("🤖 [{$this->store}] {$this->workflow}: {$this->message}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'workflow_alert',
            'store' => $this->store,
            'workflow' => $this->workflow,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
