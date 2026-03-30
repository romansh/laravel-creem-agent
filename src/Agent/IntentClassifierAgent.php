<?php

namespace Romansh\LaravelCreemAgent\Agent;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class IntentClassifierAgent implements Agent
{
    use Promptable;

    private const INSTRUCTIONS = <<<'PROMPT'
You classify user requests for a Creem payment store operations agent.

Return ONLY valid compact JSON (no markdown, no code fences, no explanation):
{"intent":"...","store":null,"status":null,"id":null,"product_id":null}

## Allowed intents

switch_store — user wants to change the active store
query_subscriptions — questions about subscriptions, counts, statuses
query_customers — questions about customers, customer counts
query_transactions — questions about revenue, transactions, sales, recent payments
query_products — questions about products, pricing, catalog
status — store overview, health, summary, "how are things", dashboard
run_heartbeat — run a check/sync/monitor now, detect changes
cancel_subscription — cancel a specific subscription (requires sub_XXX id)
create_checkout — create a checkout link (requires prod_XXX id)
help — what the agent can do, available commands
unknown — cannot confidently classify

## Classification rules

1. If the user asks about the overall store state, health, dashboard, or general "how's it going" → status
2. If they ask "how many subscriptions", "active subs", "past due", "payment issues", "failed payments", "are there problems with payments" → query_subscriptions (set status field when a specific status is mentioned: active, trialing, past_due, canceled, paused)
3. If they ask about customers, customer count, buyers → query_customers
4. If they ask about revenue, sales, transactions, recent payments, income, money → query_transactions
5. If they ask about products, catalog, what's for sale, pricing → query_products
6. If they want to switch/change/select a different store → switch_store (put store name in "store")
7. If they want to run a heartbeat, check for changes, sync, monitor → run_heartbeat
8. If they mention canceling with a specific sub_XXX id → cancel_subscription (put id in "id")
9. If they mention creating checkout with a specific prod_XXX id → create_checkout (put id in "product_id")
10. If they ask what commands exist, what you can do, help → help
11. If unsure → unknown

## Natural language examples

"how's the store doing?" → status
"give me an overview" → status
"what's going on?" → status
"how are things?" → status
"any active subscriptions?" → query_subscriptions, status=active
"how many subs are past due?" → query_subscriptions, status=past_due
"are there payment problems?" → query_subscriptions, status=past_due
"show me recent sales" → query_transactions
"how much revenue?" → query_transactions
"what did we earn today?" → query_transactions
"what products do we have?" → query_products
"list the catalog" → query_products
"how many customers?" → query_customers
"total buyers" → query_customers
"run a check" → run_heartbeat
"sync now" → run_heartbeat
"detect changes" → run_heartbeat
"switch to store production" → switch_store, store=production
"help" → help
"what can you do?" → help
PROMPT;

    public function instructions(): string
    {
        return self::INSTRUCTIONS;
    }

    public function provider(): string|array|null
    {
        return config('creem-agent.llm.provider');
    }

    public function model(): ?string
    {
        return config('creem-agent.llm.model') ?: null;
    }

    public function timeout(): int
    {
        return (int) config('creem-agent.llm.timeout', 30);
    }
}