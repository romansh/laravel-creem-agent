<?php

namespace Romansh\LaravelCreemAgent\Listeners;

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
use Romansh\LaravelCreemAgent\Support\TelegramMessageSender;

class CreemWebhookTelegramNotifier
{
    public function __construct(
        private ?TelegramMessageSender $telegram = null,
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

    public function handleDisputeCreated(DisputeCreated $event): void
    {
        $this->sendIfEnabled($this->formatDisputeCreated($event->object));
    }

    public function handleRefundCreated(RefundCreated $event): void
    {
        $this->sendIfEnabled($this->formatRefundCreated($event->object));
    }

    public function handleSubscriptionActive(SubscriptionActive $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionActive($event->object));
    }

    public function handleSubscriptionCanceled(SubscriptionCanceled $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionCanceled($event->object));
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionCreated($event->object));
    }

    public function handleSubscriptionExpired(SubscriptionExpired $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionExpired($event->object));
    }

    public function handleSubscriptionPaid(SubscriptionPaid $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionPaid($event->object));
    }

    public function handleSubscriptionPaused(SubscriptionPaused $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionPaused($event->object));
    }

    public function handleSubscriptionScheduledCancel(SubscriptionScheduledCancel $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionScheduledCancel($event->object));
    }

    public function handleSubscriptionTrialing(SubscriptionTrialing $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionTrialing($event->object));
    }

    public function handleSubscriptionUpdated(SubscriptionUpdate $event): void
    {
        $this->sendIfEnabled($this->formatSubscriptionUpdated($event->object));
    }

    private function sendIfEnabled(?string $message): void
    {
        if ($message === null || trim($message) === '') {
            return;
        }

        ($this->telegram ?? app(TelegramMessageSender::class))->send($message);
    }

    private function formatCheckoutCompleted(array $object): string
    {
        $product = $object['product']['name'] ?? 'Product';
        $email = $object['customer']['email'] ?? $object['customer_email'] ?? 'unknown customer';
        $amount = $this->formatMoney($object['amount'] ?? $object['product']['price'] ?? null, $object['currency'] ?? $object['product']['currency'] ?? 'USD');

        return $this->formatMessage('✅ New sale', [
            "Product: {$product}",
            "Amount: {$amount}",
            "Customer: {$email}",
        ]);
    }

    private function formatSubscriptionPastDue(array $object): string
    {
        $subscriptionId = $object['id'] ?? $object['subscription_id'] ?? 'unknown subscription';

        return $this->formatMessage('⚠️ Subscription past due', [
            "Subscription: {$subscriptionId}",
            'Status: past_due',
            $this->customerLine($object),
        ]);
    }

    private function formatPaymentFailed(array $object): string
    {
        $subscriptionId = $object['subscription_id'] ?? $object['subscription']['id'] ?? 'unknown subscription';
        $amount = $this->formatMoney($object['amount'] ?? null, $object['currency'] ?? 'USD');

        return $this->formatMessage('⚠️ Payment failed', [
            "Subscription: {$subscriptionId}",
            "Amount: {$amount}",
            $this->customerLine($object),
        ]);
    }

    private function formatDisputeCreated(array $object): string
    {
        return $this->formatMessage('🚨 Dispute created', [
            $this->idLine('Dispute', $object['id'] ?? null),
            $this->amountLine($object),
            $this->customerLine($object),
        ]);
    }

    private function formatRefundCreated(array $object): string
    {
        return $this->formatMessage('↩️ Refund created', [
            $this->idLine('Refund', $object['id'] ?? null),
            $this->amountLine($object),
            $this->customerLine($object),
        ]);
    }

    private function formatSubscriptionActive(array $object): string
    {
        return $this->formatSubscriptionMessage('✅ Subscription active', $object, 'active');
    }

    private function formatSubscriptionCanceled(array $object): string
    {
        return $this->formatSubscriptionMessage('❌ Subscription canceled', $object, 'canceled');
    }

    private function formatSubscriptionCreated(array $object): string
    {
        return $this->formatSubscriptionMessage('🆕 Subscription created', $object);
    }

    private function formatSubscriptionExpired(array $object): string
    {
        return $this->formatSubscriptionMessage('⌛ Subscription expired', $object, 'expired');
    }

    private function formatSubscriptionPaid(array $object): string
    {
        return $this->formatMessage('💸 Subscription paid', [
            $this->idLine('Subscription', $this->subscriptionId($object)),
            $this->amountLine($object),
            $this->customerLine($object),
        ]);
    }

    private function formatSubscriptionPaused(array $object): string
    {
        return $this->formatSubscriptionMessage('⏸️ Subscription paused', $object, 'paused');
    }

    private function formatSubscriptionScheduledCancel(array $object): string
    {
        return $this->formatSubscriptionMessage('🗓️ Subscription scheduled to cancel', $object, 'scheduled_cancel');
    }

    private function formatSubscriptionTrialing(array $object): string
    {
        return $this->formatSubscriptionMessage('🧪 Subscription trialing', $object, 'trialing');
    }

    private function formatSubscriptionUpdated(array $object): string
    {
        return $this->formatSubscriptionMessage('🔄 Subscription updated', $object);
    }

    private function formatSubscriptionMessage(string $title, array $object, ?string $fallbackStatus = null): string
    {
        $status = $object['status'] ?? $fallbackStatus;

        return $this->formatMessage($title, [
            $this->idLine('Subscription', $this->subscriptionId($object)),
            $status !== null ? "Status: {$status}" : null,
            $this->amountLine($object),
            $this->customerLine($object),
        ]);
    }

    private function formatMessage(string $title, array $lines): string
    {
        $details = array_values(array_filter($lines, static fn ($line) => is_string($line) && trim($line) !== ''));

        return implode("\n", array_merge([$title], $details));
    }

    private function subscriptionId(array $object): string
    {
        return $object['id']
            ?? $object['subscription_id']
            ?? $object['subscription']['id']
            ?? 'unknown subscription';
    }

    private function idLine(string $label, mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? "{$label}: {$value}" : null;
    }

    private function amountLine(array $object): ?string
    {
        $amount = $object['amount'] ?? $object['product']['price'] ?? null;
        $currency = $object['currency'] ?? $object['product']['currency'] ?? 'USD';

        return $amount !== null ? 'Amount: '.$this->formatMoney($amount, $currency) : null;
    }

    private function customerLine(array $object): ?string
    {
        $email = $object['customer']['email'] ?? $object['customer_email'] ?? null;

        return is_string($email) && trim($email) !== '' ? "Customer: {$email}" : null;
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