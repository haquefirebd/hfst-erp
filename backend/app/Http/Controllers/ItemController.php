<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Exception;

class ItemController extends Controller
{
    /**
     * Get a list of all products/items.
     */
    public function index(): JsonResponse
    {
        $items = Item::orderBy('sku', 'asc')->get();
        return response()->json([
            'success' => true,
            'items' => $items
        ]);
    }

    /**
     * Add a new product to the catalog.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'sku' => 'required|string|unique:items,sku',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:Serialized,BatchManaged,Standard,KitAssembly',
            'unit' => 'nullable|string|max:50',
            'initial_quantity' => 'nullable|numeric|min:0',
            'average_consumption_rate' => 'nullable|numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
        ]);

        $item = Item::create([
            'sku' => $validatedData['sku'],
            'name' => $validatedData['name'],
            'type' => $validatedData['type'],
            'unit' => $validatedData['unit'] ?? 'Pcs',
            'initial_quantity' => $validatedData['initial_quantity'] ?? 0.0000,
            'average_consumption_rate' => $validatedData['average_consumption_rate'] ?? 0.0000,
            'lead_time_days' => $validatedData['lead_time_days'] ?? 30,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'item' => $item
        ], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        $validatedData = $request->validate([
            'sku' => 'required|string|unique:items,sku,' . $id,
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'average_consumption_rate' => 'nullable|numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
        ]);

        $item->update([
            'sku' => $validatedData['sku'],
            'name' => $validatedData['name'],
            'unit' => $validatedData['unit'] ?? $item->unit,
            'average_consumption_rate' => $validatedData['average_consumption_rate'] ?? $item->average_consumption_rate,
            'lead_time_days' => $validatedData['lead_time_days'] ?? $item->lead_time_days,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'item' => $item
        ]);
    }

    /**
     * Delete a product after ensuring no transactional history exists.
     */
    public function destroy(string $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        // Integrity Check: Check if item is referenced in any database table
        $hasLedger = DB::table('stock_ledger')->where('item_id', $id)->exists();
        $hasSerials = DB::table('serialized_assets')->where('item_id', $id)->exists();
        $hasBatches = DB::table('batches')->where('item_id', $id)->exists();
        $hasOrderItems = DB::table('sales_order_items')->where('item_id', $id)->exists();
        $hasChallanItems = DB::table('delivery_challan_items')->where('item_id', $id)->exists();

        if ($hasLedger || $hasSerials || $hasBatches || $hasOrderItems || $hasChallanItems) {
            return response()->json([
                'success' => false,
                'message' => 'Data Integrity Blocked: This product cannot be deleted because it has historical stock ledger entries or sales order transactions.'
            ], 409); // 409 Conflict
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.'
        ]);
    }
}
