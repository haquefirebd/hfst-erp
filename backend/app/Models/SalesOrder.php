<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasUuids;

    protected $table = 'sales_orders';

    protected $fillable = [
        'quote_version_id',
        'project_id',
        'status',
    ];

    public function quoteVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class, 'quote_version_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function deliveryChallans(): HasMany
    {
        return $this->hasMany(DeliveryChallan::class, 'sales_order_id');
    }
}
