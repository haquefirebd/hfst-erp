<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;

class SerializedAsset extends Model
{
    use HasUuids;

    protected $table = 'serialized_assets';

    protected $fillable = [
        'item_id',
        'serial_number',
        'warranty_expiry_date',
        'installation_date',
        'compliance_cert_url',
        'status',
    ];

    protected $casts = [
        'warranty_expiry_date' => 'date',
        'installation_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'serialized_asset_id');
    }

    protected static function booted()
    {
        static::saving(function (SerializedAsset $asset) {
            $validStatuses = ['In-Stock', 'Dispatched', 'Installed', 'Under-Warranty', 'Scrapped'];
            if (!in_array($asset->status, $validStatuses, true)) {
                throw new Exception("Invalid status '{$asset->status}' assigned to SerializedAsset.");
            }
        });
    }
}
