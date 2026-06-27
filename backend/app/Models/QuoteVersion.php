<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteVersion extends Model
{
    use HasUuids;

    protected $table = 'quote_versions';

    protected $fillable = [
        'quote_id',
        'version_number',
        'parent_quote_version_id',
        'status',
        'grand_total',
        'vat_amount',
        'terms_conditions',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'grand_total' => 'decimal:4',
        'vat_amount' => 'decimal:4',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class, 'parent_quote_version_id');
    }

    public function childVersions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class, 'parent_quote_version_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'quote_version_id');
    }
}
