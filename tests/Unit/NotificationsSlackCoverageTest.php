<?php

namespace Illuminate\Notifications\Messages;

if (!\class_exists('Illuminate\\Notifications\\Messages\\SlackMessage')) {
    class SlackAttachment
    {
        public array $fields = [];

        public function fields(array $fields): self
        {
            $this->fields = $fields;
            return $this;
        }
    }

    class SlackMessage
    {
        public array $payload = [];

        public function success(): self
        {
            $this->payload['level'] = 'success';
            return $this;
        }

        public function warning(): self
        {
            $this->payload['level'] = 'warning';
            return $this;
        }

        public function content(string $content): self
        {
            $this->payload['content'] = $content;
            return $this;
        }

        public function attachment(callable $callback): self
        {
            $attachment = new SlackAttachment();
            $callback($attachment);
            $this->payload['fields'] = $attachment->fields;
            return $this;
        }
    }
}

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Notifications\FirstHeartbeat;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatAlert;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatSummary;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;

class NotificationsSlackCoverageTest extends TestCase
{
    public function test_notifications_to_slack_cover_message_builders()
    {
        config()->set('creem-agent.notifications.slack_webhook_url', 'https://hooks');

        $first = new FirstHeartbeat('store-1', [
            'customerCount' => 4,
            'transactionCount' => 2,
            'subscriptions' => ['active' => 1, 'trialing' => 2, 'past_due' => 3],
        ]);
        $firstMessage = $first->toSlack(null);
        $this->assertSame('success', $firstMessage->payload['level']);
        $this->assertStringContainsString('store-1', $firstMessage->payload['content']);
        $this->assertSame('⚠️ 3', $firstMessage->payload['fields']['Past due']);

        foreach (['good_news' => '💰', 'warning' => '⚠️', 'alert' => '🚨', 'info' => 'ℹ️'] as $severity => $emoji) {
            $alert = new HeartbeatAlert('store-1', ['message' => 'changed', 'severity' => $severity]);
            $message = $alert->toSlack(null);
            $this->assertStringContainsString($emoji, $message->payload['content']);
        }

        $summary = new HeartbeatSummary('store-1', [['message' => 'one'], ['message' => 'two']]);
        $summaryMessage = $summary->toSlack(null);
        $this->assertStringContainsString("• one\n• two", $summaryMessage->payload['content']);

        $workflow = new WorkflowAlert('store-1', 'WorkflowX', 'did something', ['x' => 1]);
        $workflowMessage = $workflow->toSlack(null);
        $this->assertSame('warning', $workflowMessage->payload['level']);
        $this->assertStringContainsString('WorkflowX', $workflowMessage->payload['content']);
    }
}
