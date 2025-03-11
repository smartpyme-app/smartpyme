<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Impuesto;
use App\Models\Admin\Documento;

class OrdenPago extends Model
{
    use HasFactory;

    protected $table = 'ordenes_pago';
    protected $fillable = [
        'id_orden',
        'id_usuario',
        'id_orden_n1co',
        'id_autorizacion_3ds',
        'autorizacion_url',
        'id_plan',
        'payment_id',
        'charge_id',
        'item_id',
        'nombre_cliente',
        'email_cliente',
        'telefono_cliente',
        'plan',
        'monto',
        'estado',
        'divisa',
        'codigo_autorizacion',
        'fecha_transaccion',
        'id_venta',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'id_plan');
    }

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'id_pago', 'payment_id');
    }

    public function venta()
    {
        return $this->hasOne(Venta::class, 'id_venta');
    }

    public function updateStatusAuthentication3DS($authenticationId, $authenticationUrl, $status)
    {
        return $this->update([
            'estado' => $status,
            'id_autorizacion_3ds' => $authenticationId,
            'autorizacion_url' => $authenticationUrl
        ]);
    }

    public function generarVenta()
    {
        if ($this->id_venta) {
           throw new \Exception('Ya se ha generado una venta para esta orden');
        }

        $documento = Documento::where('id_empresa', 2)->where('nombre', 'Factura')->first();
        $id_cliente = $this->usuario()->first()->empresa()->first()->id_cliente;
        $producto = $this->plan()->first()->producto()->first();

        if (!$documento) {
           throw new \Exception('No hay documento');
        }
        if (!$id_cliente) {
           throw new \Exception('La empresa no esta vinculada a un cliente');
        }
        if (!$producto) {
           throw new \Exception('El plan no esta vinculado a un producto');
        }

        $venta = Venta::create([
            'fecha' => date('Y-m-d'),
            'correlativo' => $documento->correlativo,
            'estado' => 'Pagada',
            'id_canal' => 185,
            'id_documento' => $documento->id,
            'forma_pago' => 'N1co',
            'condicion' => 'Contado',
            'fecha_pago' => date('Y-m-d'),
            'fecha_expiracion' => date('Y-m-d'),
            'monto_pago' => $this->monto,
            'cambio' => 0,
            'iva_percibido' => 0,
            'iva_retenido' => 0,
            'iva' => $this->monto - ($this->monto / 1.13),
            'total_costo' => 0,
            'descuento' => 0,
            'sub_total' => $this->monto / 1.13,
            'gravada' => $this->monto / 1.13,
            'total' => $this->monto,
            'id_bodega' => 76,
            'id_cliente' => $id_cliente,
            'id_usuario' => 114,
            'id_vendedor' => $this->id_usuario,
            'id_empresa' => 2,
            'id_sucursal' => 76
        ]);

        Detalle::create([
            'id_producto' => $producto->id,
            'descripcion' => $producto->nombre,
            'cantidad' => 1,
            'precio' => $this->monto,
            'costo' => 0,
            'descuento' => 0,
            'gravada' => $this->monto,
            'total_costo' => 0,
            'total' => $this->monto,
            'id_venta' => $venta->id,
        ]);

        Impuesto::create([
            'id_impuesto' => 108,
            'monto' => $venta->iva,
            'id_venta' => $venta->id,
        ]);

        Documento::findOrFail($venta->id_documento)->increment('correlativo');

        $this->id_venta = $venta->id;
        $this->save();

        return $venta;
    }


}
