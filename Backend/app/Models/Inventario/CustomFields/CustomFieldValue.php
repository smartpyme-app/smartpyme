<?php

namespace App\Models\Inventario\CustomFields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldValue extends Model
{
    protected $fillable = [
        'custom_field_id',
        'value'
    ];

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function productCustomFields(): HasMany
    {
        return $this->hasMany(ProductCustomField::class, 'custom_field_id');
    }
}