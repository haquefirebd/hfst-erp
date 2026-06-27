<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\StockLedger;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    private string $authToken = 'Bearer hfst_erp_admin_secret_token_2026';

    /**
     * Test retrieving all products.
     */
    public function test_can_retrieve_products(): void
    {
        Item::create([
            'sku' => 'FM200-TEST',
            'name' => 'FM200 Cylinder Test',
            'type' => 'Standard',
        ]);

        $response = $this->getJson('/api/items');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(1, 'items');
    }

    /**
     * Test creating a product without token is blocked.
     */
    public function test_cannot_create_product_without_token(): void
    {
        $response = $this->postJson('/api/items', [
            'sku' => 'PANEL-NFX',
            'name' => 'Control Panel Test',
            'type' => 'Serialized',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test creating a product with valid token succeeds.
     */
    public function test_can_create_product_with_token(): void
    {
        $response = $this->withHeader('Authorization', $this->authToken)
                         ->postJson('/api/items', [
                             'sku' => 'PANEL-NFX-99',
                             'name' => 'Control Panel Test 99',
                             'type' => 'Serialized',
                             'unit' => 'Sets',
                             'initial_quantity' => 12,
                             'average_consumption_rate' => 0.5,
                             'lead_time_days' => 60,
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('item.sku', 'PANEL-NFX-99')
                 ->assertJsonPath('item.unit', 'Sets')
                 ->assertJsonPath('item.initial_quantity', '12.0000');

        $this->assertDatabaseHas('items', [
            'sku' => 'PANEL-NFX-99',
            'unit' => 'Sets',
            'initial_quantity' => '12.0000'
        ]);
    }

    /**
     * Test modifying a product.
     */
    public function test_can_modify_product(): void
    {
        $item = Item::create([
            'sku' => 'SPRINK-TEST',
            'name' => 'Sprinkler Head Test',
            'type' => 'Standard',
        ]);

        $response = $this->withHeader('Authorization', $this->authToken)
                         ->putJson('/api/items/' . $item->id, [
                             'sku' => 'SPRINK-TEST-MOD',
                             'name' => 'Sprinkler Head Test Modified',
                             'average_consumption_rate' => 5.0,
                             'lead_time_days' => 15,
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseHas('items', ['sku' => 'SPRINK-TEST-MOD']);
    }

    /**
     * Test data integrity check prevents deletion of product with transaction history.
     */
    public function test_deletion_blocked_if_referenced_in_ledger(): void
    {
        $item = Item::create([
            'sku' => 'CYL-FM200',
            'name' => 'FM200 Cylinder',
            'type' => 'Standard',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Transit Central',
            'location' => 'Port Ground',
        ]);

        // Create transaction history
        StockLedger::create([
            'item_id' => $item->id,
            'dest_warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'transaction_type' => 'GRN',
        ]);

        // Attempt deletion
        $response = $this->withHeader('Authorization', $this->authToken)
                         ->deleteJson('/api/items/' . $item->id);

        $response->assertStatus(409)
                 ->assertJsonPath('success', false)
                 ->assertJsonFragment([
                     'message' => 'Data Integrity Blocked: This product cannot be deleted because it has historical stock ledger entries or sales order transactions.'
                 ]);

        // Confirm product still exists
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }
}
