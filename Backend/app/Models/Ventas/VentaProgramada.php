<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class VentaProgramada extends Model
{
    protected $table = 'ventas_programadas';

    protected $fillable = [
        'id_empresa',
        'id_cliente',
        'id_documento',
        'id_sucursal',
        'id_bodega',
        'id_vendedor',
        'forma_pago',
        'condicion',
        'sub_total',
        'descuento',
        'iva',
        'total',
        'gravada',
        'exenta',
        'no_sujeta',
        'observaciones',
        'frecuencia',
        'dia_ejecucion',
        'hora_ejecucion',
        'proxima_ejecucion',
        'activo',
        'emitir_automatico',
        'enviar_email',
        'max_ejecuciones',
        'ejecuciones_count',
        'id_usuario',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'emitir_automatico' => 'boolean',
        'enviar_email' => 'boolean',
        'proxima_ejecucion' => 'datetime',
        'dia_ejecucion' => 'integer',
        'max_ejecuciones' => 'integer',
        'ejecuciones_count' => 'integer',
    ];

    public function detalles()
    {
        return $this->hasMany(DetalleVentaProgramada::class, 'id_venta_programada');
    }

    public function ejecuciones()
    {
        return $this->hasMany(VentaProgramadaEjecucion::class, 'id_venta_programada');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'id_cliente');
    }

    public function documento()
    {
        return $this->belongsTo('App\Models\Admin\Documento', 'id_documento');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function bodega()
    {
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function vendedor()
    {
        return $this->belongsTo('App\Models\User', 'id_vendedor');
    }

    public function usuario()
    {
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }
}