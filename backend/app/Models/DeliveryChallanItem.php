<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChallanItem extends Model
{
    use HasUuids;

    protected $table = 'delivery_challan_items';

    protected $fillable = [
        'delivery_challan_id',
        'item_id',
        'serialized_asset_id',
        'batch_id',
        'shipped_qty',
    ];

    protected $casts = [
        'shipped_qty' => 'decimal:4',
    ];

    public function deliveryChallan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function serializedAsset(): BelongsTo
    {
        return $this->belongsTo(SerializedAsset::class, 'serialized_asset_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'delivery_challan_item_id');
    }
}
