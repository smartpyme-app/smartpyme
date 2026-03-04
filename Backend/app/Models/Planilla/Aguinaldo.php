<?php

namespace App\Models\Planilla;

use App\Constants\PlanillaConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Aguinaldo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id_empresa',
        'id_sucursal',
        'anio',
        'fecha_calculo',
        'total_aguinaldos',
        'total_retenciones',
        'estado',
        'observaciones'
    ];

    protected $casts = [
        'anio' => 'integer',
        'fecha_calculo' => 'date',
        'total_aguinaldos' => 'decimal:2',
        'total_retenciones' => 'decimal:2',
        'estado' => 'integer'
    ];

    // Relaciones
    public function aguinaldoDetalles()
    {
        return $this->hasMany(AguinaldoDetalle::class, 'id_aguinaldo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    // Scopes
    public function scopePorAnio($query, $anio)
    {
        return $query->where('anio', $anio);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('id_sucursal', $sucursalId);
    }

    public function scopeBorrador($query)
    {
        return $query->where('estado', PlanillaConstants::AGUINALDO_BORRADOR);
    }

    public function scopePagado($query)
    {
        return $query->where('estado', PlanillaConstants::AGUINALDO_PAGADO);
    }

    // Métodos
    /**
     * Actualiza los totales del aguinaldo basándose en los detalles
     */
    public function actualizarTotales()
    {
        $detalles = $this->aguinaldoDetalles;

        // Calcular total de aguinaldos brutos
        $this->total_aguinaldos = $detalles->sum('monto_aguinaldo_bruto');

        // Calcular total de retenciones
        $this->total_retenciones = $detalles->sum('retencion_renta');

        $this->save();
    }

    /**
     * Obtiene el total neto de aguinaldos (bruto - retenciones)
     */
    public function getTotalNetoAttribute()
    {
        return $this->total_aguinaldos - $this->total_retenciones;
    }

    /**
     * Verifica si el aguinaldo está en estado borrador
     */
    public function esBorrador()
    {
        return $this->estado == PlanillaConstants::AGUINALDO_BORRADOR;
    }

    /**
     * Verifica si el aguinaldo está pagado
     */
    public function estaPagado()
    {
        return $this->estado == PlanillaConstants::AGUINALDO_PAGADO;
    }
}
