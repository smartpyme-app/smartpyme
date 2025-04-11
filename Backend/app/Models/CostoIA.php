<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostoIA extends Model
{
    use HasFactory;

    protected $table = 'costos_ia';

    protected $fillable = [
        'id_usuario',
        'id_empresa',
        'modelo',
        'tokens_entrada',
        'tokens_salida',
        'costo_estimado',
        'consulta',
        'respuesta'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}
