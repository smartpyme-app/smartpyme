<?php

namespace App\Models\FidelizacionClientes;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoClienteEmpresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_cliente_empresa';

    protected $fillable = [
        'id_empresa',
        'id_tipo_base',
        'nivel',
        'nombre_personalizado',
        'activo',
        'puntos_por_dolar',
        'valor_punto',
        'minimo_canje',
        'maximo_canje',
        'expiracion_meses',
        'configuracion_avanzada',
        'is_default',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'nivel' => 'integer',
        'is_default' => 'boolean',
        'puntos_por_dolar' => 'decimal:4',
        'valor_punto' => 'decimal:4',
        'minimo_canje' => 'integer',
        'maximo_canje' => 'integer',
        'expiracion_meses' => 'integer',
        'configuracion_avanzada' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function tipoBase()
    {
        return $this->belongsTo(TipoClienteBase::class, 'id_tipo_base');
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'id_tipo_cliente');
    }

    public function getNombreEfectivoAttribute()
    {
        return $this->nombre_personalizado ?: ($this->tipoBase->nombre ?? 'Sin Tipo');
    }

    public function getDescripcionEfectivaAttribute()
    {
        return $this->tipoBase->descripcion ?? 'Tipo personalizado';
    }

    public function getCodeEfectivoAttribute()
    {
        return $this->tipoBase->code ?? 'CUSTOM';
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDefaults($query)
    {
        return $query->where('is_default', true);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }

    public function scopePersonalizados($query)
    {
        return $query->whereNull('id_tipo_base');
    }

    public function scopeBasados($query)
    {
        return $query->whereNotNull('id_tipo_base');
    }

    public function scopePorNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }

    public function scopeStandard($query)
    {
        return $query->where('nivel', 1);
    }

    public function scopeVip($query)
    {
        return $query->where('nivel', 2);
    }

    public function scopeUltra($query)
    {
        return $query->where('nivel', 3);
    }

    public function calcularPuntos($monto)
    {
        return floor($monto * $this->puntos_por_dolar);
    }

    public function calcularDescuento($puntos)
    {
        // Por defecto: 1 punto = $0.01
        $valorPunto = $this->configuracion_avanzada['valor_punto'] ?? 0.01;
        return $puntos * $valorPunto;
    }

    public function puedeUsarPuntos($puntos)
    {
        return $puntos >= $this->minimo_canje && $puntos <= $this->maximo_canje;
    }

    public function getFechaExpiracion()
    {
        return now()->addMonths($this->expiracion_meses);
    }

    public function getConfiguracionPersonalizada($key, $default = null)
    {
        return $this->configuracion_avanzada[$key] ?? $default;
    }

    public function isPersonalizado()
    {
        return is_null($this->id_tipo_base);
    }

    public function isBasado()
    {
        return !is_null($this->id_tipo_base);
    }

    public function isStandard()
    {
        return $this->nivel === 1;
    }

    public function isVip()
    {
        return $this->nivel === 2;
    }

    public function isUltra()
    {
        return $this->nivel === 3;
    }

    public function getNivelNombre()
    {
        switch ($this->nivel) {
            case 1:
                return 'Standard';
            case 2:
                return 'VIP';
            case 3:
                return 'Ultra VIP';
            default:
                return 'Personalizado';
        }
    }
}
