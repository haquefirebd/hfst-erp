<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $table = 'projects';

    protected $fillable = [
        'parent_project_id',
        'name',
        'client_name',
        'location',
    ];

    public function parentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_project_id');
    }

    public function subProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'parent_project_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'project_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'project_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'project_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'project_id');
    }

    public function accountsReceivableEntries(): HasMany
    {
        return $this->hasMany(AccountsReceivableLedger::class, 'project_id');
    }
}
