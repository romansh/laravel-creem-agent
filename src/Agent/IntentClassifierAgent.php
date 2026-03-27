<?php

namespace Romansh\LaravelCreemAgent\Agent;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class IntentClassifierAgent implements Agent
{
    use Promptable;

    private const INSTRUCTIONS = <<<'PROMPT'
You classify user requests for a Creem store operations agent.

Return only valid compact JSON with this shape:
{"intent":"...","store":null,"status":null,"id":null,"product_id":null}

Allowed intents:
- switch_store
- query_subscriptions
- query_customers
- query_transactions
- query_products
- status
- run_heartbeat
- cancel_subscription
- create_checkout
- help
- unknown

Rules:
- Use switch_store only when the user wants to change the active store. Put the target store in "store".
- Use query_subscriptions for subscription counts and status questions. Put the subscription status in "status" when known.
- Map payment issues / failed payments / past due requests to query_subscriptions with status "past_due".
- Use query_customers for customer counts and customer list questions.
- Use query_transactions for revenue, sales, and transactions questions.
- Use query_products for product listing questions.
- Use status for overview, health, summary, or current store status questions.
- Use run_heartbeat for monitor, heartbeat, sync, or check-now requests.
- Use cancel_subscription only when a concrete subscription id like sub_123 is present. Put it into "id".
- Use create_checkout only when a concrete product id like prod_123 is present. Put it into "product_id".
- Use help when the user asks what the agent can do.
- If you are not confident, return intent "unknown".
- Never add explanations, markdown, or code fences.
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