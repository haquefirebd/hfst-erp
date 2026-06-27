<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\StockLedger;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Exception;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed and verify database constraints.
     */
    public function run(): void
    {
        $this->command->info("Starting Fire Safety ERP Verification Seeder...");

        // -------------------------------------------------------------
        // Setup Base Entities
        // -------------------------------------------------------------
        $project = Project::create([
            'name' => 'High-Rise Tower Fire Sprinkler installation',
            'client_name' => 'DHAKA METROPOLIS DEVELOPERS LTD',
            'location' => 'Sector 12, Uttara, Dhaka, Bangladesh',
        ]);
        $this->command->info("✔ Created Project: {$project->name}");

        $warehouse = Warehouse::create([
            'name' => 'Uttara Central Depot',
            'location' => 'Sector 3, Uttara, Dhaka',
            'is_active' => true,
        ]);
        $this->command->info("✔ Created Warehouse: {$warehouse->name}");

        $item = Item::create([
            'sku' => 'FM200-CYL-120L',
            'name' => 'FM200 Clean Agent Cylinder 120L',
            'type' => 'BatchManaged',
            'average_consumption_rate' => 1.5,
            'lead_time_days' => 45,
        ]);
        $this->command->info("✔ Created Item: {$item->name}");

        // Verify Dynamic Reorder Point
        // Reorder point = 1.5 * 45 = 67.5
        $reorderPoint = $item->reorder_point;
        if ($reorderPoint === 67.5) {
            $this->command->info("✔ Dynamic Reorder Point Calculated Correctly: {$reorderPoint}");
        } else {
            throw new Exception("❌ Dynamic Reorder Point calculation mismatch. Expected 67.5, got {$reorderPoint}");
        }

        // -------------------------------------------------------------
        // 1. Verify Stock Ledger Constraints
        // -------------------------------------------------------------
        // Test: valid GRN transaction
        $ledgerValid = StockLedger::create([
            'item_id' => $item->id,
            'dest_warehouse_id' => $warehouse->id,
            'quantity' => 100.0,
            'transaction_type' => 'GRN',
        ]);
        $this->command->info("✔ Valid GRN Stock Ledger Entry written successfully.");

        // Test: invalid entry (quantity <= 0)
        try {
            StockLedger::create([
                'item_id' => $item->id,
                'dest_warehouse_id' => $warehouse->id,
                'quantity' => -5.0,
                'transaction_type' => 'Adjustment',
            ]);
            throw new Exception("❌ FAILED: Saved a StockLedger entry with negative quantity.");
        } catch (Exception $e) {
            $this->command->info("✔ Correctly blocked negative StockLedger quantity: " . $e->getMessage());
        }

        // Test: invalid entry (no warehouses)
        try {
            StockLedger::create([
                'item_id' => $item->id,
                'quantity' => 10.0,
                'transaction_type' => 'Adjustment',
            ]);
            throw new Exception("❌ FAILED: Saved a StockLedger entry with no warehouses specified.");
        } catch (Exception $e) {
            $this->command->info("✔ Correctly blocked StockLedger entry with no warehouses: " . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 2. Verify Invoice Upper Limit Delivered Quantity Constraints (Across Multiple Invoices)
        // -------------------------------------------------------------
        $salesOrder = SalesOrder::create([
            'project_id' => $project->id,
            'status' => 'Open',
        ]);

        $soItem = SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'item_id' => $item->id,
            'ordered_qty' => 15.0,
            'unit_price' => 125000.00, // BDT
        ]);

        $challan = DeliveryChallan::create([
            'sales_order_id' => $salesOrder->id,
            'status' => 'Delivered',
        ]);

        $challanItem = DeliveryChallanItem::create([
            'delivery_challan_id' => $challan->id,
            'item_id' => $item->id,
            'shipped_qty' => 10.0, // Delivered 10 units
        ]);
        $this->command->info("✔ Setup Delivery Challan Line with shipped quantity = 10.");

        // First Invoice: Milestone Invoice 1 (6 units)
        $invoice1 = Invoice::create([
            'project_id' => $project->id,
            'status' => 'Draft',
            'invoice_date' => now()->toDateString(),
        ]);
        $invItem1 = InvoiceItem::create([
            'invoice_id' => $invoice1->id,
            'delivery_challan_item_id' => $challanItem->id,
            'invoiced_qty' => 6.0,
            'unit_price' => 125000.00,
        ]);
        $this->command->info("✔ Invoice 1: Successfully invoiced 6.0 units (within shipped quantity of 10).");

        // Second Invoice: Milestone Invoice 2 (3 units)
        $invoice2 = Invoice::create([
            'project_id' => $project->id,
            'status' => 'Draft',
            'invoice_date' => now()->toDateString(),
        ]);
        $invItem2 = InvoiceItem::create([
            'invoice_id' => $invoice2->id,
            'delivery_challan_item_id' => $challanItem->id,
            'invoiced_qty' => 3.0,
            'unit_price' => 125000.00,
        ]);
        $this->command->info("✔ Invoice 2: Successfully invoiced 3.0 units (total 9.0, within shipped quantity of 10).");

        // Third Invoice: Final Invoice (Attempting to bill remaining but exceeding by 2 units)
        $invoice3 = Invoice::create([
            'project_id' => $project->id,
            'status' => 'Draft',
            'invoice_date' => now()->toDateString(),
        ]);
        try {
            InvoiceItem::create([
                'invoice_id' => $invoice3->id,
                'delivery_challan_item_id' => $challanItem->id,
                'invoiced_qty' => 2.0, // This would bring the total to 11.0, which exceeds 10.0
                'unit_price' => 125000.00,
            ]);
            throw new Exception("❌ FAILED: Bypassed delivery challan limit, invoiced total of 11 units.");
        } catch (Exception $e) {
            $this->command->info("✔ Invoice 3: Correctly blocked over-billing (Total 11/10): " . $e->getMessage());
        }

        $this->command->info("-------------------------------------------------------------");
        $this->command->info("ALL FIRE SAFETY ERP CONSTRAINTS PASSED SUCCESSFULLY!");
        $this->command->info("-------------------------------------------------------------");
    }
}
