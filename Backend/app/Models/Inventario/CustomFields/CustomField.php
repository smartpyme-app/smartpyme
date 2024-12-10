<?php

namespace App\Models\Inventario\CustomFields;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomField extends Model
{
    protected $fillable = [
        'name',
        'field_type',
        'is_required',
        'empresa_id'
    ];

    protected $casts = [
        'is_required' => 'boolean'
    ];

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}