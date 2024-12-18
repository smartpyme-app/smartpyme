<?php
namespace App\Models\Ventas\Orden_Produccion;

use App\Models\Admin\Empresa;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Model;

class OrdenProduccion extends Model
{
    protected $table = 'ordenes_produccion';

    protected $fillable = [
        'codigo',
        'fecha',
        'fecha_entrega',
        'estado',
        'id_cotizacion_venta',
        'id_cliente',
        'id_usuario',
        'id_asesor',
        'observaciones',
        'subtotal',
        'total_costo',
        'descuento',
        'no_sujeta',
        'excenta',
        'cuenta_a_terceros',
        'gravada',
        'iva',
        'total',
        'id_empresa'
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_entrega' => 'date',
        'subtotal' => 'decimal:2',
        'total_costo' => 'decimal:2',
        'descuento' => 'decimal:2',
        'no_sujeta' => 'decimal:2',
        'excenta' => 'decimal:2',
        'cuenta_a_terceros' => 'decimal:2',
        'gravada' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    // Relaciones
    public function detalles()
    {
        return $this->hasMany(DetalleOrdenProduccion::class, 'id_orden_produccion');
    }

    public function historial()
    {
        return $this->hasMany(HistorialOrdenProduccion::class, 'id_orden_produccion');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function asesor()
    {
        return $this->belongsTo(User::class, 'id_asesor');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}