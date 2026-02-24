<?php

namespace App\Models\FidelizacionClientes;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuntosCliente extends Model
{
    use HasFactory;

    protected $table = 'puntos_cliente';

    protected $fillable = [
        'id_cliente',
        'id_empresa',
        'puntos_disponibles',
        'puntos_totales_ganados',
        'puntos_totales_canjeados',
        'fecha_ultima_actividad',
    ];

    protected $casts = [
        'puntos_disponibles' => 'integer',
        'puntos_totales_ganados' => 'integer',
        'puntos_totales_canjeados' => 'integer',
        'fecha_ultima_actividad' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function scopeConPuntosDisponibles($query)
    {
        return $query->where('puntos_disponibles', '>', 0);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }

    public function scopePorEmpresaConLicencia($query, $empresaId)
    {
        $empresa = Empresa::find($empresaId);
        
        if ($empresa && ($empresa->esEmpresaPadre() || $empresa->esEmpresaHija())) {
            // Si la empresa tiene licencia, obtener puntos de todas las empresas de la licencia
            $empresasLicenciaIds = $empresa->getEmpresasLicenciaIds();
            return $query->whereIn('id_empresa', $empresasLicenciaIds);
        }
        
        return $query->where('id_empresa', $empresaId);
    }

    public function scopeActivosRecientes($query, $dias = 30)
    {
        return $query->where('fecha_ultima_actividad', '>=', now()->subDays($dias));
    }

    public function actualizarActividad()
    {
        $this->update(['fecha_ultima_actividad' => now()]);
    }

    public function agregarPuntos($puntos)
    {
        $this->increment('puntos_disponibles', $puntos);
        $this->increment('puntos_totales_ganados', $puntos);
        $this->actualizarActividad();
    }

    public function restarPuntos($puntos)
    {
        if ($this->puntos_disponibles >= $puntos) {
            $this->decrement('puntos_disponibles', $puntos);
            $this->increment('puntos_totales_canjeados', $puntos);
            $this->actualizarActividad();
            return true;
        }
        return false;
    }

    public function getPorcentajeUso()
    {
        if ($this->puntos_totales_ganados == 0) {
            return 0;
        }
        return round(($this->puntos_totales_canjeados / $this->puntos_totales_ganados) * 100, 2);
    }

    public function getDiasInactividad()
    {
        if (!$this->fecha_ultima_actividad) {
            return null;
        }
        return $this->fecha_ultima_actividad->diffInDays(now());
    }

    public function isInactivo($dias = 90)
    {
        $diasInactividad = $this->getDiasInactividad();
        return $diasInactividad && $diasInactividad > $dias;
    }
}
