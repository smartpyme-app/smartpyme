<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_compra';
    protected $fillable = array(
        'id_producto',
        'id_presentacion',
        'lote_id',
        'cantidad',
        'costo',
        'descuento',
        'no_sujeta',
        'exenta',
        'iva',
        'porcentaje_impuesto',
        'subtotal',
        'total',
        'id_compra'

    );

    protected $appends = ['nombre_producto', 'img', 'codigo', 'inventario_por_lotes'];

    public function getNombreProductoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
    }

    public function getImgAttribute(){
        $producto = $this->producto()->withoutGlobalScopes()->first();
        return $producto ? $producto->img : 'productos/default.jpg';
    }

    public function getcodigoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('codigo')->first();
    }

    public function getInventarioPorLotesAttribute(){
        return (bool) $this->producto()->withoutGlobalScopes()->pluck('inventario_por_lotes')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','id_compra');
    }

    public function lote(){
        return $this->belongsTo('App\Models\Inventario\Lote','lote_id');
    }

    public function loteAsignaciones(){
        return $this->hasMany(DetalleCompraLote::class, 'id_detalle_compra');
    }

    public function presentacion(){
        return $this->belongsTo('App\Models\Inventario\ProductoPresentacion', 'id_presentacion');
    }


}
