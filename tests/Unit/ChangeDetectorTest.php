<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Heartbeat\ChangeDetector;

class ChangeDetectorTest extends TestCase
{
    public function test_detect_builds_changes_for_transactions_and_transitions_and_customers()
    {
        $det = new ChangeDetector();

        $previous = [];
        $txnResult = ['newTransactions' => [
            ['product' => 'P', 'amount' => 1234, 'customer_email' => 'a@b'],
        ]];

        $subResult = ['transitions' => [
            ['type' => 'new', 'subscription_id' => 's1', 'to' => 'active'],
            ['type' => 'alert', 'subscription_id' => 's2', 'from' => 'active', 'to' => 'canceled'],
            ['type' => 'warning', 'subscription_id' => 's3', 'from' => 'active', 'to' => 'past_due'],
            ['type' => 'good_news', 'subscription_id' => 's4', 'from' => 'past_due', 'to' => 'active'],
            ['type' => 'alert', 'subscription_id' => 's5', 'from' => 'active', 'to' => 'canceled'],
            ['type' => 'alert', 'subscription_id' => 's6', 'from' => 'active', 'to' => 'canceled'],
        ]];

        $custResult = ['newCount' => 2, 'totalCount' => 10];

        $changes = $det->detect($previous, $txnResult, $subResult, $custResult);

        $this->assertNotEmpty($changes);
        $types = array_column($changes, 'type');
        $this->assertContains('new_transaction', $types);
        $this->assertContains('subscription_new', $types);
        $this->assertContains('subscription_alert', $types);
        $this->assertContains('new_customers', $types);
        $this->assertContains('churn_spike', $types);
    }
}
