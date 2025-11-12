<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model
{

    protected $table = 'detalles_venta';
    protected $fillable = array(
        'id_producto',
        'descripcion',
        'cantidad',
        'precio',
        'precio_sin_iva',
        'precio_con_iva',
        'costo',
        'descuento',
        'no_sujeta',
        'exenta',
        'gravada',
        'cuenta_a_terceros',
        'total_costo',
        'total',
        'id_venta',
        'id_vendedor',
        'iva',
    );

    protected $appends = ['nombre_producto', 'img', 'codigo', 'descuento_porcentaje', 'marca'];

    public function getNombreProductoAttribute()
    {
        if ($this->descripcion) {
            return $this->descripcion;
        } else {
            $paquete = $this->paquete()->first();
            if ($paquete) {
                return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first() . ' Numero: ' . $paquete->wr . ' Guia: ' . $paquete->num_guia;
            }
            return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
        }
    }

    public function getCodigoAttribute(){
        return $this->producto()->pluck('codigo')->first();
    }

    public function getMarcaAttribute(){
        return $this->producto()->pluck('marca')->first();
    }

    public function getDescripcionAttribute($value){
        if (is_null($value)) {
            $paquete = $this->paquete()->first();
            if ($paquete) {
                return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first() . ' Numero: ' . $paquete->wr . ' Guia: ' . $paquete->num_guia;
            }
            return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
        } else {
            return $value;
        }
    }

    public function getImgAttribute()
    {
        return $this->producto()->withoutGlobalScopes()->first() ? $this->producto()->withoutGlobalScopes()->first()->img : null;
    }

    public function producto()
    {
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function venta()
    {
        return $this->belongsTo('App\Models\Ventas\Venta', 'id_venta');
    }

    public function composiciones()
    {
        return $this->hasMany('App\Models\Ventas\DetalleCompuesto', 'id_detalle');
    }

    public function paquete()
    {
        return $this->hasOne('App\Models\Inventario\Paquete', 'id_venta_detalle');
    }

    public function vendedor()
    {
        return $this->belongsTo('App\Models\User', 'id_vendedor');
    }

    public function getDescuentoPorcentajeAttribute()
    {

        if ($this->subtotal == 0) {
            return 0;
        }

        $porcentaje = ($this->descuento / $this->subtotal) * 100;

        return round($porcentaje, 2);
    }
}
