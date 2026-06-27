<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Projects (Master Project Model)
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_project_id')->nullable();
            $table->string('name');
            $table->string('client_name');
            $table->text('location');
            $table->timestamps();

            $table->foreign('parent_project_id')->references('id')->on('projects')->onDelete('restrict');
        });

        // 2. Items / SKUs
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->enum('type', ['Serialized', 'BatchManaged', 'Standard', 'KitAssembly']);
            $table->string('unit')->default('Pcs');
            $table->decimal('initial_quantity', 12, 4)->default(0.0000);
            $table->decimal('average_consumption_rate', 12, 4)->default(0.0000);
            $table->integer('lead_time_days')->default(30);
            $table->timestamps();
        });

        // 3. Warehouses
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('location');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Serialized Assets (For NFPA 72 Panels, Pumps, etc.)
        Schema::create('serialized_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->string('serial_number')->unique();
            $table->date('warranty_expiry_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->string('compliance_cert_url', 512)->nullable();
            $table->string('status', 50)->default('In-Stock');
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
        });

        // 5. Batches / Lots (For Dry Powders, Clean Agent Cylinders, Foam)
        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->string('batch_number');
            $table->date('manufacturing_date');
            $table->date('expiry_date');
            $table->date('hydrostatic_test_due_date')->nullable();
            $table->json('supplier_traceability_info')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'batch_number']);
            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
        });

        // 6. Double-Entry Stock Ledger
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->uuid('source_warehouse_id')->nullable();
            $table->uuid('dest_warehouse_id')->nullable();
            $table->uuid('serialized_asset_id')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->uuid('project_id')->nullable();
            $table->string('transaction_type');
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
            $table->foreign('source_warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('dest_warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('serialized_asset_id')->references('id')->on('serialized_assets')->onDelete('restrict');
            $table->foreign('batch_id')->references('id')->on('batches')->onDelete('restrict');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('restrict');
        });

        // 7. Quotes & Quote Versions
        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('restrict');
        });

        Schema::create('quote_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_id');
            $table->integer('version_number');
            $table->uuid('parent_quote_version_id')->nullable();
            $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Rejected', 'Expired', 'Converted'])->default('Draft');
            $table->decimal('grand_total', 15, 4);
            $table->decimal('vat_amount', 15, 4);
            $table->text('terms_conditions')->nullable();
            $table->timestamps();

            $table->unique(['quote_id', 'version_number']);
            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('restrict');
            $table->foreign('parent_quote_version_id')->references('id')->on('quote_versions')->onDelete('restrict');
        });

        // 8. Sales Orders & Items
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_version_id')->nullable();
            $table->uuid('project_id');
            $table->enum('status', ['Draft', 'Open', 'Partially Fulfilled', 'Fulfilled', 'Closed', 'Cancelled'])->default('Draft');
            $table->timestamps();

            $table->foreign('quote_version_id')->references('id')->on('quote_versions')->onDelete('restrict');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('restrict');
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sales_order_id');
            $table->uuid('item_id');
            $table->decimal('ordered_qty', 12, 4);
            $table->decimal('unit_price', 15, 4);
            $table->timestamps();

            $table->unique(['sales_order_id', 'item_id']);
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('restrict');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
        });

        // 9. Delivery Challans & Items
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sales_order_id');
            $table->enum('status', ['Draft', 'Ready', 'Dispatched', 'Delivered', 'Returned', 'Cancelled'])->default('Draft');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('restrict');
        });

        Schema::create('delivery_challan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('delivery_challan_id');
            $table->uuid('item_id');
            $table->uuid('serialized_asset_id')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->decimal('shipped_qty', 12, 4);
            $table->timestamps();

            $table->unique(['delivery_challan_id', 'item_id', 'serialized_asset_id', 'batch_id'], 'dc_item_unique');
            $table->foreign('delivery_challan_id')->references('id')->on('delivery_challans')->onDelete('restrict');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
            $table->foreign('serialized_asset_id')->references('id')->on('serialized_assets')->onDelete('restrict');
            $table->foreign('batch_id')->references('id')->on('batches')->onDelete('restrict');
        });

        // 10. Invoices & Items (Support both Challan-based and Direct Invoicing)
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->nullable(); // Nullable for direct/retail sales
            $table->string('customer_name')->nullable(); // Direct customer name input
            $table->enum('status', ['Draft', 'Issued', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled'])->default('Draft');
            $table->string('nbr_vat_challan_no')->nullable();
            $table->date('invoice_date');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('restrict');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('delivery_challan_item_id')->nullable(); // Nullable for direct invoicing
            $table->uuid('item_id')->nullable(); // Direct item link
            $table->decimal('invoiced_qty', 12, 4);
            $table->decimal('unit_price', 15, 4);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('delivery_challan_item_id')->references('id')->on('delivery_challan_items')->onDelete('restrict');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
        });

        // 11. Accounts Receivable Ledger
        Schema::create('accounts_receivable_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('invoice_id');
            $table->decimal('amount', 15, 4);
            $table->date('due_date');
            $table->decimal('cleared_amount', 15, 4)->default(0.0000);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_receivable_ledger');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('quote_versions');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('stock_ledger');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('serialized_assets');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('items');
        Schema::dropIfExists('projects');
    }
};
