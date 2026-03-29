<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreem\Events\CheckoutCompleted;
use Romansh\LaravelCreem\Events\SubscriptionPastDue;
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
                && str_contains($request['text'], 'New sale: Demo Product ($49.00) from buyer@example.test');
        });
    }

    public function test_subscription_past_due_skips_send_in_openclaw_mode(): void
    {
        Http::fake();

        config()->set('creem-agent.telegram.mode', 'openclaw');
        config()->set('creem-agent.notifications.telegram_bot_token', 'bot-token');
        config()->set('creem-agent.notifications.telegram_chat_id', 'chat-1');

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

        Http::assertNothingSent();
    }
}