<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Exception;

class InvoiceItem extends Model
{
    use HasUuids;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'delivery_challan_item_id',
        'item_id',
        'invoiced_qty',
        'unit_price',
    ];

    protected $casts = [
        'invoiced_qty' => 'decimal:4',
        'unit_price' => 'decimal:4',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function deliveryChallanItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallanItem::class, 'delivery_challan_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    protected static function booted()
    {
        static::saving(function (InvoiceItem $invoiceItem) {
            // Only validate against delivery challans if the reference is provided.
            if ($invoiceItem->delivery_challan_item_id) {
                DB::transaction(function () use ($invoiceItem) {
                    $challanItem = DB::table('delivery_challan_items')
                        ->where('id', $invoiceItem->delivery_challan_item_id)
                        ->first();

                    if (!$challanItem) {
                        throw new Exception("Delivery Challan Item reference is invalid.");
                    }

                    $alreadyInvoicedQuery = DB::table('invoice_items')
                        ->where('delivery_challan_item_id', $invoiceItem->delivery_challan_item_id);

                    if ($invoiceItem->exists) {
                        $alreadyInvoicedQuery->where('id', '!=', $invoiceItem->id);
                    }

                    $alreadyInvoiced = (float) $alreadyInvoicedQuery->sum('invoiced_qty');
                    $newTotalInvoiced = $alreadyInvoiced + (float) $invoiceItem->invoiced_qty;

                    if ($newTotalInvoiced > (float) $challanItem->shipped_qty) {
                        $maxAllowed = (float) $challanItem->shipped_qty - $alreadyInvoiced;
                        throw new Exception(
                            sprintf(
                                "Over-billing violation. Max invoice quantity allowed for this challan line: %.4f. Attempted: %.4f.",
                                $maxAllowed,
                                $invoiceItem->invoiced_qty
                            )
                        );
                    }
                });
            }
        });
    }
}
