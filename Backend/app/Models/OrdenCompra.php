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
    protected $appends = ['nombre_proveedor', 'nombre_usuario', 'nombre_sucursal', "total_orden_compra"];
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
}
