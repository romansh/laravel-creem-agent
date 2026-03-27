# Laravel Creem Agent

Autonomous AI agent for Creem.io store monitoring — heartbeat engine, proactive workflows, multi-store support, chat interface, and notifications.

## Installation

```bash
composer require romansh/laravel-creem-agent
```

## Setup

```bash
php artisan vendor:publish --tag=creem-agent-config
php artisan vendor:publish --tag=creem-agent-migrations
php artisan migrate
php artisan creem-agent:install
```

## Quick Start

```bash
# Run first heartbeat
php artisan creem-agent:heartbeat

# Chat with agent
php artisan creem-agent:chat "how many active subscriptions?"

# Check agent status
php artisan creem-agent:status

# Run for all stores
php artisan creem-agent:heartbeat --all-stores
```

## Configuration

- See `config/creem-agent.php` for:
- Multi-store setup
- Notification channels (Telegram)
- Telegram ownership mode: `laravel` or `openclaw`
- Natural-language routing via Laravel AI SDK
- Heartbeat frequency per store
- Quiet hours
- OpenClaw integration

## AI / Natural Language Routing

The chat interface now uses `laravel/ai` to classify natural-language requests into agent intents, with the legacy regex parser kept as a fallback.

Example `.env` settings:

```env
CREEM_AGENT_LLM_ENABLED=true
CREEM_AGENT_LLM_PROVIDER=openai
CREEM_AGENT_LLM_MODEL=gpt-5.4
CREEM_AGENT_LLM_TIMEOUT=30

OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
OPENROUTER_API_KEY=
OLLAMA_BASE_URL=http://localhost:11434
```

Provider switching is done through `CREEM_AGENT_LLM_PROVIDER`, while model switching is done through `CREEM_AGENT_LLM_MODEL`.

When `CREEM_AGENT_TELEGRAM_MODE=openclaw`, Telegram should be configured in OpenClaw gateway using the native channel model from https://docs.openclaw.ai/channels/telegram. The package can render a starter snippet with:

```bash
php artisan creem-agent:openclaw-telegram-config
```

## Architecture

Uses a **CLI Facade** (`CreemCli`) that auto-detects:
1. Native `creem` CLI (brew) → fast shell exec with `--json`
2. `laravel-creem-cli` → in-process SDK calls (fallback)

Transparent failover between backends.

## Proactive Workflows

- **FailedPaymentRecovery** — alerts on `past_due` subscriptions
- **ChurnDetection** — detects cancellation spikes
- **RevenueDigest** — summarizes new transactions
- **NewCustomerWelcome** — celebrates growth
- **AnomalyDetection** — flags unusual metric drops

## License

MIT
