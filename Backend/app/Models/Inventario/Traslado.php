<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Traslado extends Model
{
    use HasFactory;
    protected $table = 'traslados';
    protected $fillable = [
        'id_producto',
        'id_bodega_de',
        'id_bodega',
        'cantidad',
        'costo',
        'id_empresa',
        'id_usuario',
        'concepto',
        'estado',
        'lote_id',
        'lote_id_destino'
    ];

    protected $appends = ['nombre_producto', 'nombre_origen', 'nombre_destino'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function getNombreProductoAttribute(){

        return $this->producto()->pluck('nombre')->first();
    }

    public function getNombreOrigenAttribute(){

        return $this->origen()->pluck('nombre')->first();
    }

    public function getNombreDestinoAttribute(){

        return $this->destino()->pluck('nombre')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function destino(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function origen(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega_de');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function lote(){
        return $this->belongsTo('App\Models\Inventario\Lote', 'lote_id');
    }

    public function loteDestino(){
        return $this->belongsTo('App\Models\Inventario\Lote', 'lote_id_destino');
    }

}
