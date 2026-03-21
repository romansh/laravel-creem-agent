<?php

namespace Romansh\LaravelCreemAgent\Agent;

class CommandParser
{
    public function parse(string $message): array
    {
        $message = strtolower(trim($message));

        // Store switching
        if (preg_match('/switch\s+(?:to\s+)?store\s+(\w+)/i', $message, $m)) {
            return ['intent' => 'switch_store', 'store' => $m[1]];
        }

        // Query subscriptions
        if (preg_match('/(?:how many|count|number of)\s+(?:active\s+)?subscriptions?/i', $message)) {
            return ['intent' => 'query_subscriptions', 'status' => 'active'];
        }
        if (preg_match('/subscriptions?\s+(?:with\s+)?status\s+(\w+)/i', $message, $m)) {
            return ['intent' => 'query_subscriptions', 'status' => $m[1]];
        }

        // Payment issues
        if (preg_match('/payment\s+(?:issues?|failures?|problems?)|past.?due/i', $message)) {
            return ['intent' => 'query_subscriptions', 'status' => 'past_due'];
        }

        // Query customers
        if (preg_match('/(?:how many|count|number of)\s+customers?/i', $message)) {
            return ['intent' => 'query_customers'];
        }

        // Revenue / transactions
        if (preg_match('/revenue|transactions?|sales?/i', $message)) {
            return ['intent' => 'query_transactions'];
        }

        // Status
        if (preg_match('/status|health|overview|summary/i', $message)) {
            return ['intent' => 'status'];
        }

        // Run heartbeat
        if (preg_match('/\b(heartbeat|check|monitor)\b/i', $message)) {
            return ['intent' => 'run_heartbeat'];
        }

        // Cancel subscription
        if (preg_match('/cancel\s+(?:subscription\s+)?(sub_\w+)/i', $message, $m)) {
            return ['intent' => 'cancel_subscription', 'id' => $m[1]];
        }

        // Create checkout
        if (preg_match('/create\s+checkout\s+(?:for\s+)?(?:product\s+)?(prod_\w+)/i', $message, $m)) {
            return ['intent' => 'create_checkout', 'product_id' => $m[1]];
        }

        // Products
        if (preg_match('/products?/i', $message)) {
            return ['intent' => 'query_products'];
        }

        // Help
        if (preg_match('/help|what can you/i', $message)) {
            return ['intent' => 'help'];
        }

        return ['intent' => 'unknown', 'message' => $message];
    }
}
