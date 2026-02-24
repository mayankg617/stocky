<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'adresse', 'email', 'phone', 'country', 'city', 'tax_number',
        'is_royalty_eligible', 'points', 'opening_balance', 'credit_limit',
    ];

    protected $casts = [
        'code' => 'integer',
        'is_royalty_eligible' => 'integer',
        'points' => 'double',
        'opening_balance' => 'double',
        'credit_limit' => 'double',
    ];

    /**
     * Get custom field values for this client
     */
    public function customFieldValues()
    {
        return $this->morphMany(CustomFieldValue::class, 'entity', 'entity_type', 'entity_id');
    }
}
