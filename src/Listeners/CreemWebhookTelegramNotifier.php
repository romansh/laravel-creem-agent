<?php

namespace Romansh\LaravelCreemAgent\Listeners;

use Romansh\LaravelCreem\Events\CheckoutCompleted;
use Romansh\LaravelCreem\Events\PaymentFailed;
use Romansh\LaravelCreem\Events\SubscriptionPastDue;
use Romansh\LaravelCreemAgent\Support\TelegramMessageSender;
use Romansh\LaravelCreemAgent\Support\TelegramModeResolver;

class CreemWebhookTelegramNotifier
{
    public function __construct(
        private ?TelegramMessageSender $telegram = null,
        private ?TelegramModeResolver $modeResolver = null,
    ) {}

    public function handleCheckoutCompleted(CheckoutCompleted $event): void
    {
        $this->sendIfEnabled($this->formatCheckoutCompleted($event->object));
    }

    public function handleSubscriptionPastDue(SubscriptionPastDue $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionPastDue($event->object));
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->sendIfEnabled($this->formatPaymentFailed($event->object));
    }

    private function sendIfEnabled(?string $message): void
    {
        if ($message === null || trim($message) === '') {
            return;
        }

        $modeResolver = $this->modeResolver ?? app(TelegramModeResolver::class);

        if (!$modeResolver->usesLaravelTransport()) {
            return;
        }

        ($this->telegram ?? app(TelegramMessageSender::class))->send($message);
    }

    private function formatCheckoutCompleted(array $object): string
    {
        $product = $object['product']['name'] ?? 'Product';
        $email = $object['customer']['email'] ?? $object['customer_email'] ?? 'unknown customer';
        $amount = $this->formatMoney($object['amount'] ?? $object['product']['price'] ?? null, $object['currency'] ?? $object['product']['currency'] ?? 'USD');

        return "✅ New sale: {$product} ({$amount}) from {$email}";
    }

    private function formatSubscriptionPastDue(array $object): string
    {
        $subscriptionId = $object['id'] ?? $object['subscription_id'] ?? 'unknown subscription';

        return "⚠️ Payment issue: {$subscriptionId} is now past_due";
    }

    private function formatPaymentFailed(array $object): string
    {
        $subscriptionId = $object['subscription_id'] ?? $object['subscription']['id'] ?? 'unknown subscription';
        $amount = $this->formatMoney($object['amount'] ?? null, $object['currency'] ?? 'USD');

        return "⚠️ Payment failed: {$subscriptionId} ({$amount})";
    }

    private function formatMoney(mixed $amount, string $currency): string
    {
        if (!is_numeric($amount)) {
            return strtoupper($currency);
        }

        return sprintf('%s%.2f', $this->currencySymbol($currency), ((float) $amount) / 100);
    }

    private function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => 'EUR ',
            'GBP' => 'GBP ',
            default => strtoupper($currency).' ',
        };
    }
}