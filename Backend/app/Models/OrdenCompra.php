<?php

namespace App\Models;

use App\Models\Authorization\Authorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrdenCompra extends Model
{
    use HasFactory;

    protected $table = "orden_compras";

    protected $fillable = array(
        "fecha",
        "id_usuario",
        "id_bodega",
        "tipo_documento",
        "id_proveedor",
        "id_proyecto",
        "id_authorization",
        "observaciones",
        "referencia",
        "estado",
        "id_empresa",
        "id_sucursal",
        "cobrar_impuestos",
        "cobrar_percepcion",
    );
    protected $appends = ['nombre_proveedor', 'nombre_usuario', 'nombre_sucursal', "total_orden_compra", "total", "sub_total", "iva", "percepcion"];
    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }
    public function bodega()
    {
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function proveedor()
    {
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor', 'id_proveedor');
    }

    public function usuario()
    {
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function detalles()
    {
        return $this->hasMany(OrdenCompraDetalle::class, 'id_orden_compra');
    }

    public function authorization()
    {
        return $this->belongsTo(Authorization::class, 'id_authorization');
    }

    public function getSaldoAttribute()
    {
        return round($this->total - $this->abonos()->where('estado', 'Confirmado')->sum('total'), 2);
    }


    public function getNombreSucursalAttribute()
    {
        if ($this->sucursal()->first()) {
            return $this->sucursal()->pluck('nombre')->first();
        }
        return '';
    }

    public function getNombreProveedorAttribute()
    {
        $proveedor = $this->proveedor()->first();
        if ($proveedor) {
            return $proveedor->tipo == 'Empresa' ? $proveedor->nombre_empresa : $proveedor->nombre . ' ' . $proveedor->apellido;
        }
        return 'Consumidor Final';
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getTotalOrdenCompraAttribute()
    {
        return $this->detalles()->sum('total');
    }

    public function getSubTotalAttribute()
    {
        // Calcular subtotal sumando todos los totales de los detalles
        // El total de cada detalle ya incluye los descuentos aplicados
        $subtotal = $this->detalles()->sum('total');
        
        return round($subtotal, 2);
    }

    public function getIvaAttribute()
    {
        // Calcular IVA (13% por defecto en El Salvador)
        // Si la orden tiene cobrar_impuestos, calcular el IVA
        if ($this->cobrar_impuestos) {
            $subtotal = $this->sub_total;
            $iva = $subtotal * 0.13; // 13% de IVA
            return round($iva, 2);
        }
        return 0;
    }

    public function getTotalAttribute()
    {
        // Total = subtotal + IVA + percepción (si aplica)
        $subtotal = $this->sub_total;
        $iva = $this->iva;
        $percepcion = $this->percepcion ?? 0;
        
        return round($subtotal + $iva + $percepcion, 2);
    }

    public function getPercepcionAttribute()
    {
        // Calcular percepción (1% si aplica)
        if ($this->cobrar_percepcion) {
            $subtotal = $this->sub_total;
            $percepcion = $subtotal * 0.01; // 1% de percepción
            return round($percepcion, 2);
        }
        return 0;
    }
}
