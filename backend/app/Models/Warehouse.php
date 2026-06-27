<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasUuids;

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sourceLedgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'source_warehouse_id');
    }

    public function destLedgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'dest_warehouse_id');
    }
}
