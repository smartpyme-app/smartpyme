<?php

namespace App\Models\Bonos;

use App\Models\Admin\Empresa;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BonoRegla extends Model
{
    protected $table = 'bono_reglas';

    const TIPO_META_FIJA = 'meta_fija';
    const TIPO_ESCALONADO = 'escalonado';

    const VENTANA_MENSUAL = 'mensual';

    const ALCANCE_GLOBAL = 'global';
    const ALCANCE_VENDEDORES = 'vendedores';

    protected $fillable = [
        'id_empresa',
        'nombre',
        'tipo',
        'ventana',
        'config',
        'activo',
        'alcance',
        'id_vendedores',
    ];

    protected $casts = [
        'config' => 'array',
        'activo' => 'boolean',
        'id_vendedores' => 'array',
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

    public function bonosGenerados()
    {
        return $this->hasMany(BonoGenerado::class, 'id_regla');
    }
}
