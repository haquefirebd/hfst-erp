<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasUuids;

    protected $table = 'items';

    protected $fillable = [
        'sku',
        'name',
        'type',
        'unit',
        'initial_quantity',
        'average_consumption_rate',
        'lead_time_days',
    ];

    protected $casts = [
        'initial_quantity' => 'decimal:4',
        'average_consumption_rate' => 'decimal:4',
        'lead_time_days' => 'integer',
    ];

    /**
     * Get the serialized assets for this item.
     */
    public function serializedAssets(): HasMany
    {
        return $this->hasMany(SerializedAsset::class, 'item_id');
    }

    /**
     * Get the batches for this item.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'item_id');
    }

    /**
     * Get the ledger entries for this item.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'item_id');
    }

    /**
     * Compute reorder point dynamically using the formula:
     * Reorder Point = Average Consumption * Lead Time
     */
    public function getReorderPointAttribute(): float
    {
        return (float) ($this->average_consumption_rate * $this->lead_time_days);
    }
}
