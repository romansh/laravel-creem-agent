#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Romansh\LaravelCreemAgent\Support\TelegramWebhookSmokeRunner;

require __DIR__.'/../tests/bootstrap.php';

$app = require __DIR__.'/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$summary = app(TelegramWebhookSmokeRunner::class)->run([
    'telegram_api_base' => getenv('CREEM_AGENT_TELEGRAM_API_BASE') ?: 'http://127.0.0.1:18081/anything',
    'telegram_token' => getenv('CREEM_AGENT_TELEGRAM_TOKEN') ?: 'tok',
    'telegram_chat_id' => getenv('CREEM_AGENT_TELEGRAM_CHAT_ID') ?: 'chat-1',
    'incoming_chat_id' => getenv('CREEM_AGENT_TELEGRAM_SMOKE_INCOMING_CHAT_ID') ?: (getenv('CREEM_AGENT_TELEGRAM_CHAT_ID') ?: 'chat-1'),
    'incoming_text' => getenv('CREEM_AGENT_TELEGRAM_SMOKE_TEXT') ?: 'smoke ping',
    'reply_text' => getenv('CREEM_AGENT_TELEGRAM_SMOKE_REPLY') ?: 'smoke reply',
]);

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

exit($summary['ok'] ? 0 : 1);