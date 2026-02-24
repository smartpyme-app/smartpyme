<?php

namespace App\Models\FidelizacionClientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoClienteBase extends Model
{
    use HasFactory;

    protected $table = 'tipos_cliente_base';

    protected $fillable = [
        'code',
        'nombre',
        'descripcion',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function tiposClienteEmpresa()
    {
        return $this->hasMany(TipoClienteEmpresa::class, 'id_tipo_base');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden');
    }

    public function scopePorCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function isStandard()
    {
        return $this->code === 'STANDARD';
    }

    public function isVip()
    {
        return $this->code === 'VIP';
    }

    public function isUltraVip()
    {
        return $this->code === 'ULTRAVIP';
    }

    public function getNivelJerarquia()
    {
        return $this->orden;
    }

}
