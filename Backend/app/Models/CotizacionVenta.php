<?php

namespace App\Models;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Orden_Produccion\OrdenProduccion;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CotizacionVenta extends Model
{
    use HasFactory;
    protected $table = 'cotizacion_ventas';
    protected $fillable = [
        "estado",
        "forma_pago",
        "observaciones",
        "descripcion_personalizada",
        "descripcion_impresion",
        "fecha_expiracion",
        //"detalle_banco",
        "fecha",
        "total_costo",
        "total",
        "sub_total",
        "no_sujeta",
        "exenta",
        "gravada",
        "cuenta_a_terceros",
        "iva",
        "iva_retenido",
        "iva_percibido",
        "descuento",
        "correlativo",
        "id_documento",
        "id_cliente",
        "id_proyecto",
        "id_bodega",
        "id_usuario",
        "id_vendedor",
        "id_empresa",
        "id_sucursal",
        "cobrar_impuestos",
        "aplicar_retencion",
        "terminos_de_venta"
    ];

    protected $appends = ['nombre_cliente', 'nombre_usuario', 'nombre_vendedor',  'nombre_sucursal', 'nombre_documento','facturada'];

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
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function getNombreDocumentoAttribute()
    {
        return $this->documento()->pluck('nombre')->first();
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
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function detalles()
    {
        return $this->hasMany(CotizacionVentaDetalle::class, 'id_cotizacion_venta');
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'id_documento');
    }

    public function tieneOrdenProduccion()
    {
        return $this->hasMany(OrdenProduccion::class, 'id_cotizacion_venta')->where('estado', '!=', 'anulada');
    }


    public function facturada()
    {
        return $this->hasOne(Venta::class, 'num_cotizacion');
    }


    public function getFacturadaAttribute()
    {
        return $this->facturada()->exists();
    }


}
