<?php

namespace Romansh\LaravelCreemAgent;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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

class CreemAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/creem-agent.php', 'creem-agent');

        $this->app->singleton('creem-cli', function () {
            return new Cli\CreemCliManager();
        });

        $this->app->singleton(Agent\AgentManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/creem-agent.php' => config_path('creem-agent.php'),
            ], 'creem-agent-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'creem-agent-migrations');

            $this->commands([
                Console\HeartbeatCommand::class,
                Console\HeartbeatResetCommand::class,
                Console\AgentStartCommand::class,
                Console\AgentStopCommand::class,
                Console\AgentStatusCommand::class,
                Console\AgentChatCommand::class,
                Console\AgentInstallCommand::class,
                Console\OpenClawTelegramConfigCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/agent.php');

        Event::listen(CheckoutCompleted::class, [CreemWebhookTelegramNotifier::class, 'handleCheckoutCompleted']);
        Event::listen(DisputeCreated::class, [CreemWebhookTelegramNotifier::class, 'handleDisputeCreated']);
        Event::listen(SubscriptionPastDue::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionPastDue']);
        Event::listen(PaymentFailed::class, [CreemWebhookTelegramNotifier::class, 'handlePaymentFailed']);
        Event::listen(RefundCreated::class, [CreemWebhookTelegramNotifier::class, 'handleRefundCreated']);
        Event::listen(SubscriptionActive::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionActive']);
        Event::listen(SubscriptionCanceled::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionCanceled']);
        Event::listen(SubscriptionCreated::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionCreated']);
        Event::listen(SubscriptionExpired::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionExpired']);
        Event::listen(SubscriptionPaid::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionPaid']);
        Event::listen(SubscriptionPaused::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionPaused']);
        Event::listen(SubscriptionScheduledCancel::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionScheduledCancel']);
        Event::listen(SubscriptionTrialing::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionTrialing']);
        Event::listen(SubscriptionUpdate::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionUpdated']);
    }
}
