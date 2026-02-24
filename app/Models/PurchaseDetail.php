<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    protected $fillable = [
        'id', 'purchase_id', 'purchase_unit_id', 'quantity', 'product_id', 'total', 'product_variant_id',
        'cost', 'TaxNet', 'discount', 'discount_method', 'tax_method',
        'imei_number', 'batch_no', 'expiry_date',
    ];

    protected $casts = [
        'total' => 'double',
        'cost' => 'double',
        'TaxNet' => 'double',
        'discount' => 'double',
        'quantity' => 'double',
        'purchase_id' => 'integer',
        'purchase_unit_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'imei_number' => 'string',
        'batch_no' => 'string',
        'expiry_date' => 'date',
    ];

    public function purchase()
    {
        return $this->belongsTo('App\Models\Purchase');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
