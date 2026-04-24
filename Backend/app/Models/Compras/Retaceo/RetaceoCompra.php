<?php

namespace App\Models\Compras\Retaceo;

use App\Models\Compras\Compra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetaceoCompra extends Model
{
    protected $table = 'retaceo_compras';

    protected $fillable = [
        'id_retaceo',
        'id_compra',
    ];

    public function retaceo(): BelongsTo
    {
        return $this->belongsTo(Retaceo::class, 'id_retaceo');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'id_compra');
    }
}
