<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function updateStatusAuthentication3DS($authenticationId, $authenticationUrl, $status)
    {
        return $this->update([
            'estado' => $status,
            'id_autorizacion_3ds' => $authenticationId,
            'autorizacion_url' => $authenticationUrl
        ]);
    }
}
