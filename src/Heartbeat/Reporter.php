<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatAlert;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatSummary;
use Romansh\LaravelCreemAgent\Notifications\FirstHeartbeat;
use Romansh\LaravelCreemAgent\Support\TelegramMessageSender;
use Romansh\LaravelCreemAgent\Support\TelegramModeResolver;

class Reporter
{
    public function __construct(
        private ?\Closure $clock = null,
        private ?\Closure $sender = null,
        private ?TelegramModeResolver $telegramMode = null,
        private ?TelegramMessageSender $telegramSender = null,
        private bool $forceTelegramDirect = false,
    ) {}

    public function reportFirstRun(string $store, array $state): void
    {
        Log::info("[CreemAgent] First heartbeat for store '{$store}'", $state);

        $notification = new FirstHeartbeat($store, $state);
        $this->send($store, $notification);
    }

    public function reportChanges(string $store, array $changes): void
    {
        if (empty($changes)) {
            Log::debug("[CreemAgent] No changes for store '{$store}'");
            return;
        }

        $count = count($changes);
        Log::info("[CreemAgent] {$count} change(s) for store '{$store}'", [
            'count' => $count,
            'types' => array_column($changes, 'type'),
        ]);

        if (count($changes) === 1) {
            $notification = new HeartbeatAlert($store, $changes[0]);
        } else {
            $notification = new HeartbeatSummary($store, $changes);
        }

        $this->send($store, $notification);
    }

    private function send(string $store, $notification): void
    {
        // Check quiet hours for non-critical notifications
        if ($this->isQuietHours() && !$this->isCritical($notification)) {
            Log::debug("[CreemAgent] Suppressing notification during quiet hours");
            return;
        }

        try {
            if ($this->sender !== null) {
                ($this->sender)($store, $notification);
                return;
            }

            $notifier = Notification::route('slack', config('creem-agent.notifications.slack_webhook_url'))
                ->route('discord', config('creem-agent.notifications.discord_webhook_url'));

            $telegramMode = $this->telegramMode ?? app(TelegramModeResolver::class);

            $notifier->notify($notification);

            if ($this->forceTelegramDirect || $telegramMode->usesLaravelTransport()) {
                $message = $this->resolveTelegramMessage($notification);

                if ($message !== null) {
                    ($this->telegramSender ?? app(TelegramMessageSender::class))->send($message);
                }
            }
        } catch (\Exception $e) {
            Log::error("[CreemAgent] Failed to send notification", ['error' => $e->getMessage()]);
        }
    }

    private function resolveTelegramMessage(object $notification): ?string
    {
        if (!method_exists($notification, 'toTelegramText')) {
            return null;
        }

        $message = $notification->toTelegramText();

        return is_string($message) && trim($message) !== '' ? $message : null;
    }

    private function isQuietHours(): bool
    {
        $hour = (int) (($this->clock ?? static fn() => now())())->format('H');
        $start = config('creem-agent.quiet_hours.start', 23);
        $end = config('creem-agent.quiet_hours.end', 7);

        if ($start > $end) {
            return $hour >= $start || $hour < $end;
        }
        return $hour >= $start && $hour < $end;
    }

    private function isCritical($notification): bool
    {
        if ($notification instanceof HeartbeatAlert) {
            return in_array($notification->change['severity'] ?? '', ['alert', 'warning']);
        }
        return false;
    }
}
