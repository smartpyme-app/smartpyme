<?php

namespace App\Models\Inventario\Traslados;

use Illuminate\Database\Eloquent\Model;

class Traslado extends Model {

    protected $table = 'producto_traslados';
    protected $fillable = array(
       'fecha',
       'nota',
       'estado',
       'origen_id',
       'destino_id',
       'usuario_id'
    );

    protected $appends = ['usuario', 'total'];

    public function getNotaAttribute($value)
    {
        return ucwords(mb_strtolower($value));
    }

    public function getUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getTotalAttribute()
    {
        return  $this->detalles()->count();
    }

    public function detalles(){
        return $this->hasMany('App\Models\Inventario\Traslados\Detalle','traslado_id');
    }

    public function origen(){
        return $this->belongsTo('App\Models\Inventario\Bodega','origen_id');
    }

    public function destino(){
        return $this->belongsTo('App\Models\Inventario\Bodega','destino_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

}



