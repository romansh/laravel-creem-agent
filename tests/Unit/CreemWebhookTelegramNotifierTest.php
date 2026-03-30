<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Romansh\LaravelCreem\Events\CheckoutCompleted;
use Romansh\LaravelCreem\Events\DisputeCreated;
use Romansh\LaravelCreem\Events\PaymentFailed;
use Romansh\LaravelCreem\Events\RefundCreated;
use Romansh\LaravelCreem\Events\SubscriptionActive;
use Romansh\LaravelCreem\Events\SubscriptionCanceled;
use Romansh\LaravelCreem\Events\SubscriptionCreated;
use Romansh\LaravelCreem\Events\SubscriptionExpired;
use Romansh\LaravelCreem\Events\SubscriptionPaid;
use Romansh\LaravelCreem\Events\SubscriptionPastDue;
use Romansh\LaravelCreem\Events\SubscriptionPaused;
use Romansh\LaravelCreem\Events\SubscriptionScheduledCancel;
use Romansh\LaravelCreem\Events\SubscriptionTrialing;
use Romansh\LaravelCreem\Events\SubscriptionUpdate;
use Romansh\LaravelCreemAgent\Listeners\CreemWebhookTelegramNotifier;

class CreemWebhookTelegramNotifierTest extends TestCase
{
    public function test_checkout_completed_sends_telegram_message_in_laravel_mode(): void
    {
        Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true], 200)]);

        config()->set('creem-agent.telegram.mode', 'laravel');
        config()->set('creem-agent.notifications.telegram_bot_token', 'bot-token');
        config()->set('creem-agent.notifications.telegram_chat_id', 'chat-1');
        config()->set('creem-agent.notifications.telegram_api_base', 'https://api.telegram.test');

        $listener = new CreemWebhookTelegramNotifier();
        $listener->handleCheckoutCompleted(new CheckoutCompleted([
            'id' => 'evt_1',
            'eventType' => 'checkout.completed',
            'created_at' => now()->getTimestampMs(),
            'object' => [
                'amount' => 4900,
                'currency' => 'USD',
                'customer_email' => 'buyer@example.test',
                'product' => ['name' => 'Demo Product'],
            ],
        ]));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.telegram.test/botbot-token/sendMessage'
                && $request['chat_id'] === 'chat-1'
                && str_contains($request['text'], "✅ New sale\nProduct: Demo Product\nAmount: $49.00\nCustomer: buyer@example.test");
        });
    }

    #[DataProvider('supportedWebhookEventsProvider')]
    public function test_supported_webhooks_send_formatted_multiline_messages_in_laravel_mode(string $method, object $event, array $expectedFragments): void
    {
        Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true], 200)]);

        config()->set('creem-agent.telegram.mode', 'laravel');
        config()->set('creem-agent.notifications.telegram_bot_token', 'bot-token');
        config()->set('creem-agent.notifications.telegram_chat_id', 'chat-1');
        config()->set('creem-agent.notifications.telegram_api_base', 'https://api.telegram.test');

        $listener = new CreemWebhookTelegramNotifier();
        $listener->{$method}($event);

        Http::assertSent(function (Request $request) use ($expectedFragments): bool {
            if ($request->url() !== 'https://api.telegram.test/botbot-token/sendMessage' || $request['chat_id'] !== 'chat-1') {
                return false;
            }

            foreach ($expectedFragments as $fragment) {
                if (!str_contains($request['text'], $fragment)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_subscription_past_due_still_sends_in_openclaw_mode_for_webhooks(): void
    {
        Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true], 200)]);

        config()->set('creem-agent.telegram.mode', 'openclaw');
        config()->set('creem-agent.notifications.telegram_bot_token', 'bot-token');
        config()->set('creem-agent.notifications.telegram_chat_id', 'chat-1');
        config()->set('creem-agent.notifications.telegram_api_base', 'https://api.telegram.test');

        $listener = new CreemWebhookTelegramNotifier();
        $listener->handleSubscriptionPastDue(new SubscriptionPastDue([
            'id' => 'evt_2',
            'eventType' => 'subscription.past_due',
            'created_at' => now()->getTimestampMs(),
            'object' => [
                'id' => 'sub_123',
                'status' => 'past_due',
            ],
        ]));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.telegram.test/botbot-token/sendMessage'
                && $request['chat_id'] === 'chat-1'
                && str_contains($request['text'], '⚠️ Subscription past due');
        });
    }

    public static function supportedWebhookEventsProvider(): array
    {
        $basePayload = [
            'id' => 'evt_test',
            'created_at' => now()->getTimestampMs(),
            'object' => [
                'id' => 'sub_123',
                'amount' => 4900,
                'currency' => 'USD',
                'status' => 'active',
                'customer' => ['email' => 'buyer@example.test'],
                'customer_email' => 'buyer@example.test',
                'product' => ['name' => 'Demo Product', 'price' => 4900, 'currency' => 'USD'],
                'subscription' => ['id' => 'sub_123'],
            ],
        ];

        $payload = static function (string $eventType, array $objectOverrides = []) use ($basePayload): array {
            return [
                'id' => $basePayload['id'],
                'eventType' => $eventType,
                'created_at' => $basePayload['created_at'],
                'object' => array_replace($basePayload['object'], $objectOverrides),
            ];
        };

        return [
            'payment failed' => [
                'handlePaymentFailed',
                new PaymentFailed($payload('payment.failed')),
                ['⚠️ Payment failed', 'Subscription: sub_123', 'Amount: $49.00'],
            ],
            'dispute created' => [
                'handleDisputeCreated',
                new DisputeCreated($payload('dispute.created')),
                ['🚨 Dispute created', 'Dispute: sub_123', 'Amount: $49.00'],
            ],
            'refund created' => [
                'handleRefundCreated',
                new RefundCreated($payload('refund.created')),
                ['↩️ Refund created', 'Refund: sub_123', 'Amount: $49.00'],
            ],
            'subscription active' => [
                'handleSubscriptionActive',
                new SubscriptionActive($payload('subscription.active')),
                ['✅ Subscription active', 'Subscription: sub_123', 'Status: active'],
            ],
            'subscription canceled' => [
                'handleSubscriptionCanceled',
                new SubscriptionCanceled($payload('subscription.canceled', ['status' => 'canceled'])),
                ['❌ Subscription canceled', 'Subscription: sub_123', 'Status: canceled'],
            ],
            'subscription created' => [
                'handleSubscriptionCreated',
                new SubscriptionCreated($payload('subscription.created', ['status' => 'trialing'])),
                ['🆕 Subscription created', 'Subscription: sub_123', 'Status: trialing'],
            ],
            'subscription expired' => [
                'handleSubscriptionExpired',
                new SubscriptionExpired($payload('subscription.expired', ['status' => 'expired'])),
                ['⌛ Subscription expired', 'Subscription: sub_123', 'Status: expired'],
            ],
            'subscription paid' => [
                'handleSubscriptionPaid',
                new SubscriptionPaid($payload('subscription.paid')),
                ['💸 Subscription paid', 'Subscription: sub_123', 'Amount: $49.00'],
            ],
            'subscription paused' => [
                'handleSubscriptionPaused',
                new SubscriptionPaused($payload('subscription.paused', ['status' => 'paused'])),
                ['⏸️ Subscription paused', 'Subscription: sub_123', 'Status: paused'],
            ],
            'subscription scheduled cancel' => [
                'handleSubscriptionScheduledCancel',
                new SubscriptionScheduledCancel($payload('subscription.scheduled_cancel', ['status' => 'scheduled_cancel'])),
                ['🗓️ Subscription scheduled to cancel', 'Subscription: sub_123', 'Status: scheduled_cancel'],
            ],
            'subscription trialing' => [
                'handleSubscriptionTrialing',
                new SubscriptionTrialing($payload('subscription.trialing', ['status' => 'trialing'])),
                ['🧪 Subscription trialing', 'Subscription: sub_123', 'Status: trialing'],
            ],
            'subscription updated' => [
                'handleSubscriptionUpdated',
                new SubscriptionUpdate($payload('subscription.updated')),
                ['🔄 Subscription updated', 'Subscription: sub_123', 'Status: active'],
            ],
        ];
    }
}