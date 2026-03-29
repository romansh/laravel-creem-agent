<?php

namespace Romansh\LaravelCreemAgent;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Romansh\LaravelCreem\Events\CheckoutCompleted;
use Romansh\LaravelCreem\Events\PaymentFailed;
use Romansh\LaravelCreem\Events\SubscriptionPastDue;
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
        Event::listen(SubscriptionPastDue::class, [CreemWebhookTelegramNotifier::class, 'handleSubscriptionPastDue']);
        Event::listen(PaymentFailed::class, [CreemWebhookTelegramNotifier::class, 'handlePaymentFailed']);
    }
}
