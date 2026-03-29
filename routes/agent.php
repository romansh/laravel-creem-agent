<?php

use Illuminate\Support\Facades\Route;
use Romansh\LaravelCreemAgent\Http\Controllers\AgentChatController;
use Romansh\LaravelCreemAgent\Http\Controllers\AgentStatusController;
use Romansh\LaravelCreemAgent\Support\TelegramModeResolver;

Route::prefix('creem-agent')->middleware(['web'])->group(function () {
    Route::get('/status', [AgentStatusController::class, 'index'])->name('creem-agent.status');
    Route::post('/chat', [AgentChatController::class, 'handle'])->name('creem-agent.chat');
    if (config('creem-agent.openclaw.enabled')) {
        Route::post('/openclaw/heartbeat', [\Romansh\LaravelCreemAgent\Http\Controllers\OpenClawController::class, 'heartbeat'])->name('creem-agent.openclaw.heartbeat');
    }
    if (app(TelegramModeResolver::class)->usesLaravelTransport()) {
        Route::post('/telegram/webhook', [\Romansh\LaravelCreemAgent\Http\Controllers\TelegramWebhookController::class, 'handle'])->name('creem-agent.telegram.webhook');
    }
});
