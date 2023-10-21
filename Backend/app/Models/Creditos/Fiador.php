<?php

namespace App\Models\Creditos;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Fiador extends Model {
    
    protected $table = 'credito_fiadores';
    protected $fillable = [
        'credito_id',
        'cliente_id',
        'nota',
    ];

    protected $appends = ['nombre_cliente'];

    public function getNombreClienteAttribute() 
    {
        return $this->cliente()->pluck('nombre')->first();
    }
    
    public function credito() 
    {
        return $this->belongsTo('App\Models\Creditos\Credito', 'credito_id');
    }

    public function cliente() 
    {
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'cliente_id');
    }

}
