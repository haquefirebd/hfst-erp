<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountsReceivableLedger extends Model
{
    use HasUuids;

    protected $table = 'accounts_receivable_ledger';

    protected $fillable = [
        'project_id',
        'invoice_id',
        'amount',
        'due_date',
        'cleared_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'due_date' => 'date',
        'cleared_amount' => 'decimal:4',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Get the outstanding balance of this entry.
     */
    public function getBalanceAttribute(): float
    {
        return (float) ($this->amount - $this->cleared_amount);
    }
}
