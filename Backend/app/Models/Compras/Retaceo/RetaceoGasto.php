<?php

namespace App\Models\Compras\Retaceo;

use App\Models\Compras\Gastos\Gasto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetaceoGasto extends Model
{
    use HasFactory;

    protected $table = 'retaceo_gastos';

    protected $fillable = [
        'id_retaceo',
        'id_gasto',
        'tipo_gasto',
        'monto'
    ];

    /**
     * Relación con el retaceo
     */
    public function retaceo()
    {
        return $this->belongsTo(Retaceo::class, 'id_retaceo');
    }

    /**
     * Relación con el gasto
     */
    public function gasto()
    {
        return $this->belongsTo(Gasto::class, 'id_gasto');
    }
}