<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transformacion extends Model
{
    use HasFactory;

    protected $table = 'transformaciones';

    protected $fillable = [
        'id_usuario',
        'id_bodega',
        'fecha',
        'observacion',
    ];

    public function detalles()
    {
        return $this->hasMany(TransformacionDetalle::class, 'id_transformacion');
    }

    public function usuario()
    {
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function bodega()
    {
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }
}
