<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;

class Batch extends Model
{
    use HasUuids;

    protected $table = 'batches';

    protected $fillable = [
        'item_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'hydrostatic_test_due_date',
        'supplier_traceability_info',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'hydrostatic_test_due_date' => 'date',
        'supplier_traceability_info' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'batch_id');
    }

    protected static function booted()
    {
        static::saving(function (Batch $batch) {
            if ($batch->manufacturing_date && $batch->expiry_date) {
                if ($batch->expiry_date->lte($batch->manufacturing_date)) {
                    throw new Exception("Batch expiration date must be strictly after its manufacturing date.");
                }
            }
        });
    }
}
