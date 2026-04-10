<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\ArtisanCliDriver;
use Illuminate\Support\Facades\Http;

class ArtisanCliDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('creem.profiles.default', [
            'api_key' => 'test-key',
        ]);
        config()->set('creem.api_url', 'https://api.example.test');
        config()->set('creem.test_api_url', 'https://api.example.test');
    }

    public function test_transactions_list_and_get()
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/transactions/search')) {
                return Http::response([
                    'items' => [['id' => 'tx_1']],
                    'pagination' => ['total_records' => 1],
                    'total' => 1,
                ], 200);
            }

            if (str_contains($url, '/transactions')) {
                return Http::response(['id' => 'tx_2'], 200);
            }

            return Http::response([], 200);
        });

        $driver = new ArtisanCliDriver();

        $list = $driver->execute('transactions', 'list', ['page' => 2, 'limit' => 5]);
        $this->assertEquals(['items' => [['id' => 'tx_1']], 'pagination' => ['total_records' => 1], 'total' => 1], $list);

        $get = $driver->execute('transactions', 'get', ['id' => 'tx_2']);
        $this->assertEquals(['id' => 'tx_2'], $get);
    }

    public function test_transactions_list_passes_generic_filters_to_sdk(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/transactions/search')) {
                return Http::response([
                    'items' => [['id' => 'tx_1']],
                    'pagination' => ['total_records' => 4],
                    'total' => 4,
                ], 200);
            }

            return Http::response([], 200);
        });

        $driver = new ArtisanCliDriver();

        $list = $driver->execute('transactions', 'list', [
            'filters' => [
                'customer_id' => 'cust_1',
                'product_id' => 'prod_1',
                'order_id' => 'ord_1',
                'status' => 'paid',
            ],
            'page' => 2,
            'limit' => 5,
        ]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/transactions/search')
                && ($data['customer_id'] ?? null) === 'cust_1'
                && ($data['product_id'] ?? null) === 'prod_1'
                && ($data['order_id'] ?? null) === 'ord_1'
                && ($data['status'] ?? null) === 'paid'
                && ($data['page_number'] ?? null) === 2
                && ($data['page_size'] ?? null) === 5;
        });

        $this->assertSame(4, $list['pagination']['total_records']);
    }

    public function test_subscriptions_actions()
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/subscriptions/search')) {
                return Http::response(['items' => [], 'pagination' => ['total_records' => 0], 'total' => 0], 200);
            }

            if (str_contains($url, '/subscriptions/') && str_ends_with($url, '/cancel')) {
                return Http::response(['cancelled' => true], 200);
            }

            if (str_contains($url, '/subscriptions/') && str_ends_with($url, '/pause')) {
                return Http::response(['paused' => true], 200);
            }

            if (str_contains($url, '/subscriptions/') && str_ends_with($url, '/resume')) {
                return Http::response(['resumed' => true], 200);
            }

            if (str_contains($url, '/subscriptions') && ! str_contains($url, '/cancel')) {
                return Http::response(['id' => 'sub_1'], 200);
            }

            return Http::response([], 200);
        });

        $driver = new ArtisanCliDriver();

        $list = $driver->execute('subscriptions', 'list', ['page' => 1, 'limit' => 10]);
        $this->assertEquals(['items' => [], 'pagination' => ['total_records' => 0], 'total' => 0], $list);

        $get = $driver->execute('subscriptions', 'get', ['id' => 'sub_1']);
        $this->assertEquals(['id' => 'sub_1'], $get);

        $cancel = $driver->execute('subscriptions', 'cancel', ['id' => 'sub_1', 'mode' => 'scheduled']);
        $this->assertEquals(['cancelled' => true], $cancel);

        $pause = $driver->execute('subscriptions', 'pause', ['id' => 'sub_1']);
        $this->assertEquals(['paused' => true], $pause);

        $resume = $driver->execute('subscriptions', 'resume', ['id' => 'sub_1']);
        $this->assertEquals(['resumed' => true], $resume);
    }

    public function test_customers_list_get_and_billing()
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/customers/list')) {
                return Http::response(['items' => [], 'pagination' => ['total_records' => 0], 'total' => 0], 200);
            }

            if (str_contains($url, '/customers') && str_contains($url, 'email=')) {
                return Http::response(['id' => 'by_email'], 200);
            }

            if (str_contains($url, '/customers') && str_contains($url, 'customer_id')) {
                return Http::response(['id' => 'by_id'], 200);
            }

            if (str_contains($url, '/customers/billing')) {
                return Http::response(['customer_portal_link' => 'https://portal.test/customer'], 200);
            }

            return Http::response([], 200);
        });

        $driver = new ArtisanCliDriver();

        $list = $driver->execute('customers', 'list', []);
        $this->assertEquals(['items' => [], 'pagination' => ['total_records' => 0], 'total' => 0], $list);

        $getByEmail = $driver->execute('customers', 'get', ['email' => 'x@example.test']);
        $this->assertEquals(['id' => 'by_email'], $getByEmail);

        $getById = $driver->execute('customers', 'get', ['id' => 'cust_1']);
        $this->assertEquals(['id' => 'by_id'], $getById);

        $billing = $driver->execute('customers', 'billing', ['id' => 'cust_1']);
        $this->assertEquals(['portal_url' => 'https://portal.test/customer'], $billing);
    }

    public function test_products_checkouts_and_discounts()
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/products/search')) {
                return Http::response(['items' => [], 'pagination' => ['total_records' => 0], 'total' => 0], 200);
            }

            if (str_contains($url, '/products') && $request->method() === 'POST') {
                return Http::response(['created' => true], 200);
            }

            if (str_contains($url, '/products') && str_contains($url, 'product_id')) {
                return Http::response(['id' => 'prod_1'], 200);
            }

            if (str_contains($url, '/checkouts') && $request->method() === 'POST') {
                return Http::response(['checkout_id' => 'chk_1'], 200);
            }

            if (str_contains($url, '/checkouts')) {
                return Http::response(['id' => 'chk_1'], 200);
            }

            if (str_contains($url, '/discounts')) {
                return Http::response(['id' => 'disc_1'], 200);
            }

            return Http::response([], 200);
        });

        $driver = new ArtisanCliDriver();

        $products = $driver->execute('products', 'list', []);
        $this->assertEquals(['items' => [], 'pagination' => ['total_records' => 0], 'total' => 0], $products);

        $productGet = $driver->execute('products', 'get', ['id' => 'prod_1']);
        $this->assertEquals(['id' => 'prod_1'], $productGet);

        $productCreate = $driver->execute('products', 'create', ['name' => 'Test']);
        $this->assertEquals(['created' => true], $productCreate);

        $checkoutCreate = $driver->execute('checkouts', 'create', ['product_id' => 'prod_1']);
        $this->assertEquals(['checkout_id' => 'chk_1'], $checkoutCreate);

        $checkoutGet = $driver->execute('checkouts', 'get', ['id' => 'chk_1']);
        $this->assertEquals(['id' => 'chk_1'], $checkoutGet);

        $discount = $driver->execute('discounts', 'get', ['id' => 'disc_1']);
        $this->assertEquals(['id' => 'disc_1'], $discount);
    }

    public function test_checkout_create_preserves_regional_pricing_fields_through_agent_proxy(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/checkouts') && $request->method() === 'POST') {
                $data = $request->data();

                return Http::response([
                    'checkout_id' => 'chk_fx_1',
                    'price_id' => $data['price_id'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'payment_method_id' => $data['payment_method_id'] ?? null,
                    'off_session' => $data['off_session'] ?? null,
                ], 200);
            }

            return Http::response([], 200);
        });

        $driver = new ArtisanCliDriver();

        $result = $driver->execute('checkouts', 'create', [
            'price_id' => 'price_inr_monthly',
            'currency' => 'INR',
            'amount' => 49900,
            'payment_method_id' => 'pm_saved_123',
            'off_session' => true,
        ]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/checkouts')
                && $request->method() === 'POST'
                && ($data['price_id'] ?? null) === 'price_inr_monthly'
                && ($data['currency'] ?? null) === 'INR'
                && ($data['amount'] ?? null) === 49900
                && ($data['payment_method_id'] ?? null) === 'pm_saved_123'
                && ($data['off_session'] ?? null) === true;
        });

        $this->assertSame('price_inr_monthly', $result['price_id']);
        $this->assertSame('INR', $result['currency']);
        $this->assertSame(49900, $result['amount']);
        $this->assertTrue($result['off_session']);
    }

    public function test_unknown_resource_and_action_throw()
    {
        $driver = new ArtisanCliDriver();

        $this->expectException(\InvalidArgumentException::class);
        $driver->execute('unknown', 'list', []);
    }
}
