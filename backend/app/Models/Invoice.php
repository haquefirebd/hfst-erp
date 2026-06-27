<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuids;

    protected $table = 'invoices';

    protected $fillable = [
        'project_id',
        'customer_name',
        'status',
        'nbr_vat_challan_no',
        'invoice_date',
    ];

    protected $casts = [
        'invoice_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function accountsReceivableLedgerEntries(): HasMany
    {
        return $this->hasMany(AccountsReceivableLedger::class, 'invoice_id');
    }
}
