<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_endpoint_returns_list(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        OrderItem::factory(3)->for($order)->create();

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'total',
                    'customer' => ['id', 'name', 'email'],
                    'items'    => [
                        '*' => ['id', 'product_name', 'sku', 'quantity', 'unit_price'],
                    ],
                ],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => ['path', 'per_page', 'next_cursor', 'prev_cursor'],
        ]);
    }

    public function test_orders_endpoint_returns_empty_list_when_no_orders(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.next_cursor', null);
    }

    public function test_order_includes_correct_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        Order::factory()->for($customer)->create();

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.customer.name', 'Jane Doe');
        $response->assertJsonPath('data.0.customer.email', 'jane@example.com');
    }

    public function test_query_count_is_constant_regardless_of_order_count(): void
    {
        Customer::factory(3)->create()->each(function (Customer $customer) {
            Order::factory(4)->for($customer)->has(OrderItem::factory(2), 'items')->create();
        });

        // orders + customers + items, however many orders exist
        $this->expectsDatabaseQueryCount(3);

        $this->getJson('/api/orders')->assertStatus(200)->assertJsonCount(12, 'data');
    }

    public function test_does_not_lazy_load_relations(): void
    {
        Order::factory(2)->has(OrderItem::factory(2), 'items')->create();

        Model::preventLazyLoading();

        try {
            $this->withoutExceptionHandling()->getJson('/api/orders')->assertStatus(200);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_each_order_has_its_own_customer_and_items(): void
    {
        [$alice, $bob] = Customer::factory(2)->create();
        $first  = Order::factory()->for($alice)->create();
        $second = Order::factory()->for($bob)->create();
        $third  = Order::factory()->for($alice)->create();
        $firstItems = OrderItem::factory(1)->for($first)->create();
        $thirdItems = OrderItem::factory(3)->for($third)->create();

        $response = $this->getJson('/api/orders')->assertStatus(200);

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame($alice->id, $byId[$first->id]['customer']['id']);
        $this->assertSame($bob->id, $byId[$second->id]['customer']['id']);
        $this->assertSame($alice->id, $byId[$third->id]['customer']['id']);
        $this->assertSame($firstItems->pluck('id')->all(), array_column($byId[$first->id]['items'], 'id'));
        $this->assertSame([], $byId[$second->id]['items']);
        $this->assertSame($thirdItems->pluck('id')->all(), array_column($byId[$third->id]['items'], 'id'));
    }

    public function test_order_without_items_returns_empty_items_array(): void
    {
        Order::factory()->create();

        $this->getJson('/api/orders')
            ->assertStatus(200)
            ->assertJsonPath('data.0.items', []);
    }

    public function test_orders_are_returned_in_id_order(): void
    {
        $customer = Customer::factory()->create();
        foreach ([3, 1, 2] as $id) {
            Order::factory()->for($customer)->create(['id' => $id]);
        }

        $response = $this->getJson('/api/orders')->assertStatus(200);

        $this->assertSame([1, 2, 3], array_column($response->json('data'), 'id'));
    }

    public function test_returns_exact_field_values_with_money_as_two_decimal_strings(): void
    {
        $customer = Customer::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $order = Order::factory()->for($customer)->create(['status' => 'paid', 'total' => 12.5]);
        $item = OrderItem::factory()->for($order)->create([
            'product_name' => 'Widget',
            'sku'          => 'WG-0001',
            'quantity'     => 2,
            'unit_price'   => 6.25,
        ]);

        $response = $this->getJson('/api/orders')->assertStatus(200);

        $this->assertSame([[
            'id'       => $order->id,
            'status'   => 'paid',
            'total'    => '12.50',
            'customer' => ['id' => $customer->id, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
            'items'    => [[
                'id'           => $item->id,
                'product_name' => 'Widget',
                'sku'          => 'WG-0001',
                'quantity'     => 2,
                'unit_price'   => '6.25',
            ]],
        ]], $response->json('data'));
    }

    public function test_default_page_size_is_50_with_next_cursor(): void
    {
        Order::factory(51)->for(Customer::factory())->create();

        $this->getJson('/api/orders')
            ->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.prev_cursor', null)
            ->assertJson(['meta' => ['next_cursor' => true]]);
    }

    public function test_following_next_cursor_returns_remaining_orders_without_overlap(): void
    {
        $orders = Order::factory(7)->for(Customer::factory())->create();

        $first = $this->getJson('/api/orders?per_page=5')->assertStatus(200);
        $second = $this->getJson('/api/orders?per_page=5&cursor=' . $first->json('meta.next_cursor'))
            ->assertStatus(200)
            ->assertJsonPath('meta.next_cursor', null);

        $seen = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
        $this->assertSame($orders->pluck('id')->sort()->values()->all(), $seen);
    }

    public function test_per_page_is_respected(): void
    {
        Order::factory(8)->for(Customer::factory())->create();

        $this->getJson('/api/orders?per_page=5')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_invalid_per_page_is_rejected_with_422(): void
    {
        foreach (['0', '101', 'abc'] as $perPage) {
            $this->getJson('/api/orders?per_page=' . $perPage)
                ->assertStatus(422)
                ->assertJsonValidationErrors('per_page');
        }
    }

    public function test_query_count_stays_constant_on_later_pages(): void
    {
        Order::factory(6)->for(Customer::factory())->has(OrderItem::factory(2), 'items')->create();
        $cursor = $this->getJson('/api/orders?per_page=3')->json('meta.next_cursor');

        // cursor pagination: no COUNT(*), no OFFSET — still orders + customers + items
        $this->expectsDatabaseQueryCount(3);

        $this->getJson('/api/orders?per_page=3&cursor=' . $cursor)->assertStatus(200)->assertJsonCount(3, 'data');
    }
}
