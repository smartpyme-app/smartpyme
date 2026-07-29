<?php

namespace App\Models\Bonos;

use App\Models\Admin\Empresa;
use App\Models\User;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BonoEvaluacion extends Model
{
    protected $table = 'bono_evaluaciones';

    const ORIGEN_JOB = 'job';
    const ORIGEN_MANUAL = 'manual';

    protected $fillable = [
        'id_empresa',
        'periodo_inicio',
        'periodo_fin',
        'origen',
        'id_usuario',
        'resumen',
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
        'resumen' => 'array',
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

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
