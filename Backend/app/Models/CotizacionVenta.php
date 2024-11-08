<?php

namespace App\Models;

use App\Models\Inventario\Producto;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionVenta extends Model
{
    use HasFactory;
    protected $table = 'cotizacion_ventas';
    protected $fillable = [
        "estado",
        "observaciones",
        "fecha_expiracion",
        "fecha",
        "total",
        "correlativo",
        "id_documento",
        "id_cliente",
        "id_proyecto",
        "id_usuario",
        "id_vendedor",
        "id_empresa",
        "id_sucursal",
        "cobrar_impuestos",
        "aplicar_retencion"
    ];

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
}
