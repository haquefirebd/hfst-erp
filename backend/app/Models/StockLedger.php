<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Exception;

class StockLedger extends Model
{
    use HasUuids;

    protected $table = 'stock_ledger';

    protected $fillable = [
        'item_id',
        'source_warehouse_id',
        'dest_warehouse_id',
        'serialized_asset_id',
        'batch_id',
        'quantity',
        'project_id',
        'transaction_type',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'dest_warehouse_id');
    }

    public function serializedAsset(): BelongsTo
    {
        return $this->belongsTo(SerializedAsset::class, 'serialized_asset_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    protected static function booted()
    {
        static::saving(function (StockLedger $entry) {
            // 1. Quantity must be strictly positive
            if ($entry->quantity <= 0) {
                throw new Exception("Stock Ledger quantity must be strictly greater than 0.");
            }

            // 2. Either source_warehouse_id or dest_warehouse_id must be defined
            if (is_null($entry->source_warehouse_id) && is_null($entry->dest_warehouse_id)) {
                throw new Exception("Stock Ledger movement must define at least a source or destination warehouse.");
            }

            // 3. Serialized asset check: quantity must be exactly 1
            if (!is_null($entry->serialized_asset_id) && (float) $entry->quantity !== 1.0) {
                throw new Exception("Stock Ledger entries referencing a Serialized Asset must have a quantity of exactly 1.0.");
            }
        });
    }
}
