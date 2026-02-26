<?php

namespace App\Models\Planilla;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanillaVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'empleado_id',
        'empresa_id',
        'planilla_id',
        'fecha',
        'tipo',
        'cantidad',
        'valor_unitario',
        'monto_total',
        'concepto',
        'observaciones',
        'procesado'
    ];

    protected $dates = ['fecha'];

    // Relaciones
    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function planilla()
    {
        return $this->belongsTo(Planilla::class);
    }

    // Scopes
    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorPeriodo($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    public function scopeNoProcesados($query)
    {
        return $query->where('procesado', false);
    }

    // Métodos
    public function calcularMontoTotal()
    {
        if ($this->cantidad) {
            $this->monto_total = $this->cantidad * $this->valor_unitario;
        } else {
            $this->monto_total = $this->valor_unitario;
        }
        return $this->monto_total;
    }
}
