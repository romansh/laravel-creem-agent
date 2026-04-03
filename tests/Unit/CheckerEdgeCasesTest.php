<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Heartbeat\SubscriptionChecker;
use Romansh\LaravelCreemAgent\Heartbeat\TransactionChecker;

class CheckerEdgeCasesTest extends TestCase
{
    public function test_subscription_checker_covers_transition_types_and_fallbacks()
    {
        $previous = [
            'subscriptions' => ['expired' => 4],
            'knownSubscriptions' => [
                'sub_warn' => 'active',
                'sub_alert' => 'active',
                'sub_good' => 'paused',
                'sub_info' => 'active',
                'sub_missing' => 'trialing',
            ],
        ];

        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listByStatus'])
            ->getMock();

        $proxy->method('listByStatus')->willReturnCallback(function ($status) {
            return match ($status) {
                'active' => ['items' => [['id' => 'sub_good'], ['id' => 'sub_new']]],
                'past_due' => ['items' => [['id' => 'sub_warn']]],
                'canceled' => ['items' => [['id' => 'sub_alert']]],
                'paused' => ['items' => [['id' => 'sub_info']]],
                'expired' => throw new \RuntimeException('fetch failed'),
                default => [],
            };
        });

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions'])
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('subscriptions')->willReturn($proxy);

        $result = (new SubscriptionChecker($cli))->check($previous);
        $types = array_column($result['transitions'], 'type');

        $this->assertContains('warning', $types);
        $this->assertContains('alert', $types);
        $this->assertContains('good_news', $types);
        $this->assertContains('info', $types);
        $this->assertContains('new', $types);
        $this->assertContains('disappeared', $types);
        $this->assertSame(4, $result['counts']['expired']);
    }

    public function test_subscription_checker_handles_non_array_items()
    {
        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listByStatus'])
            ->getMock();

        $proxy->method('listByStatus')->willReturnCallback(fn($status) => $status === 'active' ? ['items' => 'invalid'] : []);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions'])
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('subscriptions')->willReturn($proxy);

        $result = (new SubscriptionChecker($cli))->check(['subscriptions' => []]);

        $this->assertSame(0, $result['counts']['active']);
    }

    public function test_subscription_checker_keeps_known_status_on_partial_fetch_failure()
    {
        $previous = [
            'subscriptions' => ['active' => 1, 'past_due' => 0],
            'knownSubscriptions' => ['sub_active' => 'active'],
        ];

        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listByStatus'])
            ->getMock();

        $proxy->method('listByStatus')->willReturnCallback(function ($status) {
            return match ($status) {
                'active' => throw new \RuntimeException('active fetch failed'),
                default => [],
            };
        });

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions'])
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('subscriptions')->willReturn($proxy);

        $result = (new SubscriptionChecker($cli))->check($previous);

        $this->assertSame(['sub_active' => 'active'], $result['knownSubscriptions']);
        $this->assertSame([], $result['transitions']);
    }

    public function test_transaction_checker_covers_exception_unchanged_and_fallback_mapping()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions'])
            ->disableOriginalConstructor()
            ->getMock();

        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $proxy->method('list')->willReturnCallback(function () {
            static $call = 0;
            $call++;

            if ($call === 1) {
                throw new \RuntimeException('transactions down');
            }

            if ($call === 2) {
                return [
                    'items' => [['id' => 'txn_same']],
                ];
            }

            return [
                'items' => [[
                    'id' => 'txn_new',
                    'amount' => 1550,
                    'product_id' => 'prod_x',
                    'customer_email' => 'user@example.test',
                    'created_at' => '2026-01-01T00:00:00Z',
                ]],
            ];
        });

        $cli->method('transactions')->willReturn($proxy);
        $checker = new TransactionChecker($cli);

        $failed = $checker->check(['lastTransactionId' => 'txn_old', 'transactionCount' => 7]);
        $this->assertSame('txn_old', $failed['latestId']);
        $this->assertSame(7, $failed['totalCount']);

        $unchanged = $checker->check(['lastTransactionId' => 'txn_same', 'transactionCount' => 3]);
        $this->assertSame([], $unchanged['newTransactions']);
        $this->assertSame(4, $unchanged['totalCount']);

        $mapped = $checker->check(['lastTransactionId' => 'txn_old', 'transactionCount' => 2]);
        $this->assertSame('prod_x', $mapped['newTransactions'][0]['product']);
        $this->assertSame('user@example.test', $mapped['newTransactions'][0]['customer_email']);
        $this->assertSame('2026-01-01T00:00:00Z', $mapped['newTransactions'][0]['created_at']);
    }

    public function test_transaction_checker_uses_data_key_and_default_fields()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions'])
            ->disableOriginalConstructor()
            ->getMock();

        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $proxy->method('list')->willReturn([
            'data' => [['id' => 'txn_new']],
        ]);

        $cli->method('transactions')->willReturn($proxy);

        $result = (new TransactionChecker($cli))->check(['lastTransactionId' => 'txn_old', 'transactionCount' => 0]);

        $this->assertSame('USD', $result['newTransactions'][0]['currency']);
        $this->assertSame('Unknown', $result['newTransactions'][0]['product']);
        $this->assertSame('unknown', $result['newTransactions'][0]['customer_email']);
        $this->assertSame('unknown', $result['newTransactions'][0]['status']);
    }

    public function test_transaction_checker_stops_collecting_at_previous_transaction_id()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions'])
            ->disableOriginalConstructor()
            ->getMock();

        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $proxy->method('list')->willReturn([
            'items' => [
                ['id' => 'txn_new'],
                ['id' => 'txn_old'],
                ['id' => 'txn_older'],
            ],
        ]);

        $cli->method('transactions')->willReturn($proxy);

        $result = (new TransactionChecker($cli))->check(['lastTransactionId' => 'txn_old', 'transactionCount' => 4]);

        $this->assertCount(1, $result['newTransactions']);
        $this->assertSame('txn_new', $result['newTransactions'][0]['id']);
    }

    public function test_transaction_checker_prefers_pagination_total_records()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions'])
            ->disableOriginalConstructor()
            ->getMock();

        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $proxy->expects($this->once())
            ->method('list')
            ->with([], 1, 20, null)
            ->willReturn([
                'items' => [['id' => 'txn_new']],
                'pagination' => ['total_records' => 9],
            ]);

        $cli->method('transactions')->willReturn($proxy);

        $result = (new TransactionChecker($cli))->check([
            'lastTransactionId' => 'txn_old',
            'transactionCount' => 4,
        ]);

        $this->assertSame(9, $result['totalCount']);
    }

    public function test_customer_checker_handles_malformed_payload_without_crashing()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['customers'])
            ->disableOriginalConstructor()
            ->getMock();

        $proxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $proxy->method('list')->willReturn(['items' => 'not-an-array']);
        $cli->method('customers')->willReturn($proxy);

        $result = (new \Romansh\LaravelCreemAgent\Heartbeat\CustomerChecker($cli))->check([
            'customerCount' => 6,
        ]);

        $this->assertSame(6, $result['totalCount']);
        $this->assertSame(0, $result['newCount']);
    }
}
