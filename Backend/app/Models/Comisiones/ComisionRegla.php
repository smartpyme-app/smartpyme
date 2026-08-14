<?php

namespace App\Models\Comisiones;

use App\Models\Admin\Empresa;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComisionRegla extends Model
{
    protected $table = 'comision_reglas';

    public const TIPO_POR_CATEGORIA = 'por_categoria';
    public const TIPO_POR_VOLUMEN = 'por_volumen';
    public const TIPO_POR_MARGEN = 'por_margen';

    public const ALCANCE_GLOBAL = 'global';
    public const ALCANCE_INDIVIDUAL = 'individual';
    public const ALCANCE_EQUIPO = 'equipo';

    public const MOMENTO_AL_PAGAR = 'al_pagar';
    public const MOMENTO_AL_FACTURAR = 'al_facturar';
    public const MOMENTO_POR_ABONO = 'por_abono';

    protected $fillable = [
        'id_empresa',
        'nombre',
        'tipo_calculo',
        'alcance',
        'id_vendedores',
        'momento_devengo',
        'reemplaza_global',
        'config',
        'activo',
    ];

    protected $casts = [
        'id_vendedores' => 'array',
        'reemplaza_global' => 'boolean',
        'config' => 'array',
        'activo' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function categoriasConfig()
    {
        return $this->hasMany(ComisionCategoriaConfig::class, 'id_regla');
    }

    public function aplicaAVendedor(int $idVendedor): bool
    {
        if ($this->alcance === self::ALCANCE_GLOBAL) {
            return true;
        }

        $ids = array_map('intval', (array) ($this->id_vendedores ?? []));

        return in_array($idVendedor, $ids, true);
    }
}
