<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Venta extends Model
{

    protected $table = 'ventas';
    protected $fillable = array(
        'tipo_dte',
        'numero_control',
        'codigo_generacion',
        'sello_mh',
        'fecha',
        'correlativo',
        'estado',
        'detalle_banco',
        'id_canal',
        'id_documento',
        'forma_pago',
        'tipo_documento',
        'num_cotizacion',
        'condicion',
        'referencia',
        'fecha_pago',
        'fecha_expiracion',
        'monto_pago',
        'cambio',
        'iva_percibido',
        'iva_retenido',
        'iva',
        'total_costo',
        'descuento',
        'sub_total',
        'no_sujeta',
        'exenta',
        'gravada',
        'cuenta_a_terceros',
        'total',
        'observaciones',
        'recurrente',
        'cotizacion',
        'descripcion_personalizada',
        'descripcion_impresion',
        'id_caja',
        'id_proyecto',
        'id_bodega',
        'id_corte',
        'id_cliente',
        'id_usuario',
        'id_vendedor',
        'id_empresa',
        'id_sucursal',
        'dte',
        'dte_invalidacion',
        'tipo_item_export',
        'cod_incoterm',
        'incoterm',
        'recinto_fiscal',
        'regimen',
        'seguro',
        'flete',
        'no_sujeta',
        'tipo_operacion',
        'tipo_renta'
    );

    protected $appends = ['nombre_cliente', 'nombre_usuario', 'nombre_vendedor',  'nombre_sucursal', 'nombre_canal', 'nombre_documento'];
    protected $casts = ['recurrente' => 'string'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function getNombreClienteAttribute()
    {
        $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return 'Consumidor Final';
    }

    public function getDteAttribute($value)
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function getDteInvalidacionAttribute($value)
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreVendedorAttribute()
    {
        return $this->vendedor()->pluck('name')->first();
    }

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function getNombreDocumentoAttribute()
    {
        return $this->documento()->pluck('nombre')->first();
    }

    public function getNombreCanalAttribute()
    {
        return $this->canal()->pluck('nombre')->first();
    }

    public function detalleText()
    {
        $text = '';

        foreach ($this->detalles as $detalle) {
            $text .= $detalle->nombre_producto . ' X ' . $detalle->cantidad . '. ';
            if ($detalle->producto()->first()->promocion()->first()) {
                foreach ($detalle->producto()->first()->promocion()->first()->detalles()->get() as $det) {
                    $text .= ' - ' . $det->nombre_producto . ' X ' . $det->cantidad . '. ';
                }
            }
        }

        return $text;
    }

    public function getSaldoAttribute()
    {
        return round($this->total - $this->abonos()->where('estado', 'Confirmado')->sum('total'), 2);
    }

    // Relaciones

    public function cliente()
    {
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'id_cliente');
    }

    public function usuario()
    {
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function vendedor()
    {
        return $this->belongsTo('App\Models\User', 'id_vendedor');
    }

    public function bodega()
    {
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function canal()
    {
        return $this->belongsTo('App\Models\Admin\Canal', 'id_canal');
    }

    public function impuestos()
    {
        return $this->hasMany('App\Models\Ventas\Impuesto', 'id_venta');
    }

    public function metodos_de_pago()
    {
        return $this->hasMany('App\Models\Ventas\MetodoDePago', 'id_venta');
    }

    public function documento()
    {
        return $this->belongsTo('App\Models\Admin\Documento', 'id_documento');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function detalles()
    {
        return $this->hasMany('App\Models\Ventas\Detalle', 'id_venta');
    }

    public function abonos()
    {
        return $this->hasMany('App\Models\Ventas\Abono', 'id_venta');
    }

    public function devoluciones()
    {
        return $this->hasMany('App\Models\Ventas\Devoluciones\Devolucion', 'id_venta');
    }
}
