<?php

namespace App\Models\Ventas\Orden_Produccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdenProduccionDocumento extends Model
{
    protected $table = 'orden_produccion_documentos';

    protected $fillable = [
        'id_orden_produccion',
        'nombre_archivo',
        'ruta_archivo',
        'mime_type',
        'tamano',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return url('storage/' . $this->ruta_archivo);
    }

    public function orden()
    {
        return $this->belongsTo(OrdenProduccion::class, 'id_orden_produccion');
    }
}
