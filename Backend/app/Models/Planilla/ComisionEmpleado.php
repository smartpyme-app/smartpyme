<?php

namespace App\Models\Planilla;

use App\Models\User;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComisionEmpleado extends Model
{
    use HasFactory;

    protected $table = 'comisiones_empleados';

    public const ORIGEN_VENTA = 'venta';
    public const ORIGEN_MANUAL = 'manual';
    public const ORIGEN_CANJE_TARJETA = 'canje_tarjeta';

    public const ORIGENES = [
        self::ORIGEN_VENTA,
        self::ORIGEN_MANUAL,
        self::ORIGEN_CANJE_TARJETA,
    ];

    protected $fillable = [
        'id_vendedor',
        'id_empresa',
        'origen',
        'correlativo_referencia',
        'id_venta',
        'categoria',
        'base_calculo',
        'tasa_comision',
        'monto_comision',
        'fecha',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'base_calculo' => 'decimal:2',
        'tasa_comision' => 'decimal:4',
        'monto_comision' => 'decimal:2',
    ];

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }

    public function empresa()
    {
        return $this->belongsTo(\App\Models\Admin\Empresa::class, 'id_empresa');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }

    public function scopePorEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    public function scopePorVendedor($query, $idVendedor)
    {
        return $query->where('id_vendedor', $idVendedor);
    }

    public function scopePorOrigen($query, $origen)
    {
        return $query->where('origen', $origen);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        if ($fechaInicio) {
            $query->whereDate('fecha', '>=', $fechaInicio);
        }
        if ($fechaFin) {
            $query->whereDate('fecha', '<=', $fechaFin);
        }

        return $query;
    }

    public function scopePorCorrelativo($query, $correlativo)
    {
        return $query->where('correlativo_referencia', 'like', '%' . $correlativo . '%');
    }

    public static function calcularMonto(float $baseCalculo, float $tasaComision): float
    {
        return round($baseCalculo * ($tasaComision / 100), 2);
    }
}
