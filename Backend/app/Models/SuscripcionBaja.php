<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Model;

class SuscripcionBaja extends Model
{
    public const MOTIVO_CANCELACION_VOLUNTARIA = 'cancelacion_voluntaria';
    public const MOTIVO_FALTA_PAGO = 'falta_pago';
    public const MOTIVO_INACTIVIDAD = 'inactividad';

    protected $table = 'suscripcion_bajas';

    protected $fillable = [
        'suscripcion_id',
        'empresa_id',
        'usuario_id',
        'motivo',
        'fecha_baja',
        'tipo_plan',
        'monto',
        'plan_nombre',
        'empresa_nombre',
        'motivo_cancelacion',
    ];

    protected $dates = [
        'fecha_baja',
        'created_at',
        'updated_at',
    ];

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'suscripcion_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
