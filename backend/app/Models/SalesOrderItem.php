<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    use HasUuids;

    protected $table = 'sales_order_items';

    protected $fillable = [
        'sales_order_id',
        'item_id',
        'ordered_qty',
        'unit_price',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:4',
        'unit_price' => 'decimal:4',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
