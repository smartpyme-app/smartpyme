<?php

namespace App\Models\FidelizacionClientes;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaccionPuntos extends Model
{
    use HasFactory;

    protected $table = 'transacciones_puntos';

    const TIPO_GANANCIA = 'ganancia';
    const TIPO_CANJE = 'canje';
    const TIPO_AJUSTE = 'ajuste';
    const TIPO_EXPIRACION = 'expiracion';

    protected $fillable = [
        'id_cliente',
        'id_empresa',
        'id_venta',
        'tipo',
        'puntos',
        'puntos_antes',
        'puntos_despues',
        'monto_asociado',
        'puntos_consumidos',
        'descripcion',
        'fecha_expiracion',
        'idempotency_key',
    ];

    protected $casts = [
        'puntos' => 'double',
        'puntos_antes' => 'double',
        'puntos_despues' => 'double',
        'monto_asociado' => 'decimal:2',
        'puntos_consumidos' => 'integer',
        'fecha_expiracion' => 'date',
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

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }

    public function consumosComoCanje()
    {
        return $this->hasMany(ConsumoPuntos::class, 'id_canje_tx');
    }

    public function consumosComoGanancia()
    {
        return $this->hasMany(ConsumoPuntos::class, 'id_ganancia_tx');
    }

    public function scopeGanancias($query)
    {
        return $query->where('tipo', self::TIPO_GANANCIA);
    }

    public function scopeCanjes($query)
    {
        return $query->where('tipo', self::TIPO_CANJE);
    }

    public function scopeAjustes($query)
    {
        return $query->where('tipo', self::TIPO_AJUSTE);
    }

    public function scopeExpiraciones($query)
    {
        return $query->where('tipo', self::TIPO_EXPIRACION);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('id_cliente', $clienteId);
    }

    public function scopeExpirandoEn($query, $dias = 30)
    {
        return $query->where('tipo', self::TIPO_GANANCIA)
                    ->where('fecha_expiracion', '<=', now()->addDays($dias))
                    ->where('puntos_consumidos', '<', 'puntos');
    }

    public function scopeDisponiblesParaConsumo($query)
    {
        return $query->where('tipo', self::TIPO_GANANCIA)
                    ->whereRaw('puntos_consumidos < puntos')
                    ->where('fecha_expiracion', '>', now())
                    ->orderBy('fecha_expiracion');
    }

    public function getPuntosDisponibles()
    {
        if ($this->tipo !== self::TIPO_GANANCIA) {
            return 0;
        }
        return max(0, $this->puntos - $this->puntos_consumidos);
    }

    public function isExpirado()
    {
        return $this->fecha_expiracion && $this->fecha_expiracion < now();
    }

    public function isCompletamenteConsumido()
    {
        return $this->puntos_consumidos >= $this->puntos;
    }

    public function getDiasParaExpirar()
    {
        if (!$this->fecha_expiracion) {
            return null;
        }
        return now()->diffInDays($this->fecha_expiracion, false);
    }

    public function consumirPuntos($cantidad)
    {
        $disponibles = $this->getPuntosDisponibles();
        $aConsumir = min($cantidad, $disponibles);
        
        if ($aConsumir > 0) {
            $this->increment('puntos_consumidos', $aConsumir);
        }
        
        return $aConsumir;
    }

    public static function generarIdempotencyKey($clienteId, $tipo, $referencia = null)
    {
        return md5($clienteId . '_' . $tipo . '_' . ($referencia ?? time()));
    }

    public function isGanancia()
    {
        return $this->tipo === self::TIPO_GANANCIA;
    }

    public function isCanje()
    {
        return $this->tipo === self::TIPO_CANJE;
    }

    public function isAjuste()
    {
        return $this->tipo === self::TIPO_AJUSTE;
    }

    public function isExpiracion()
    {
        return $this->tipo === self::TIPO_EXPIRACION;
    }

}
