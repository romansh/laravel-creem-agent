<?php

namespace Romansh\LaravelCreemAgent\Agent;

class CommandParser implements ParsesAgentMessages
{
    public function parse(string $message): array
    {
        $message = strtolower(trim($message));

        // Store switching
        if (preg_match('/switch\s+(?:to\s+)?store\s+(\w+)/i', $message, $m)) {
            return ['intent' => 'switch_store', 'store' => $m[1]];
        }

        // Cancel subscription (must be before general subscription match)
        if (preg_match('/cancel\s+(?:subscription\s+)?(sub_\w+)/i', $message, $m)) {
            return ['intent' => 'cancel_subscription', 'id' => $m[1]];
        }

        // Create checkout (must be before general product match)
        if (preg_match('/create\s+checkout\s+(?:for\s+)?(?:product\s+)?(prod_\w+)/i', $message, $m)) {
            return ['intent' => 'create_checkout', 'product_id' => $m[1]];
        }

        // Query subscriptions — count questions
        if (preg_match('/(?:how many|count|number of)\s+(?:active\s+)?subscriptions?/i', $message)) {
            return ['intent' => 'query_subscriptions', 'status' => 'active'];
        }
        if (preg_match('/subscriptions?\s+(?:with\s+)?status\s+(\w+)/i', $message, $m)) {
            return ['intent' => 'query_subscriptions', 'status' => $m[1]];
        }

        // Payment issues / past due
        if (preg_match('/payment\s+(?:issues?|failures?|problems?)|past.?due/i', $message)) {
            return ['intent' => 'query_subscriptions', 'status' => 'past_due'];
        }

        // Query customers
        if (preg_match('/(?:how many|count|number of)\s+customers?/i', $message)) {
            return ['intent' => 'query_customers'];
        }

        // Revenue / transactions / sales
        if (preg_match('/revenue|transactions?|sales?/i', $message)) {
            return ['intent' => 'query_transactions'];
        }

        // Run heartbeat — must be before status to avoid "check" conflicting
        if (preg_match('/\b(heartbeat|monitor)\b|run\s+(?:a\s+)?(?:check|heartbeat)/i', $message)) {
            return ['intent' => 'run_heartbeat'];
        }

        // Status / overview
        if (preg_match('/\b(?:status|health|overview|summary|dashboard)\b/i', $message)) {
            return ['intent' => 'status'];
        }

        // Products
        if (preg_match('/products?/i', $message)) {
            return ['intent' => 'query_products'];
        }

        // Help
        if (preg_match('/\bhelp\b|what can you/i', $message)) {
            return ['intent' => 'help'];
        }

        // General subscription mention (after more specific patterns)
        if (preg_match('/subscriptions?/i', $message)) {
            return ['intent' => 'query_subscriptions', 'status' => 'active'];
        }

        // General customer mention
        if (preg_match('/customers?/i', $message)) {
            return ['intent' => 'query_customers'];
        }

        return ['intent' => 'unknown', 'message' => $message];
    }
}
