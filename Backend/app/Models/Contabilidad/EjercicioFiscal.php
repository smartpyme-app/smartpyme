<?php

namespace App\Models\Contabilidad;

use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class EjercicioFiscal extends Model
{
    protected $table = 'ejercicios_fiscales';

    protected $fillable = [
        'id_empresa',
        'anio_referencia',
        'estado',
        'id_partida_cierre',
        'id_partida_reversa',
        'id_usuario_cierre',
        'cerrado_en',
    ];

    protected $casts = [
        'cerrado_en' => 'datetime',
    ];

    public const ESTADO_ABIERTO = 'abierto';
    public const ESTADO_CERRADO = 'cerrado';

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

    public function partidaCierre()
    {
        return $this->belongsTo(Partida::class, 'id_partida_cierre');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(User::class, 'id_usuario_cierre');
    }

    /**
     * @param  int  $empresaId  sin scope de auth (p. ej. jobs)
     */
    public static function estaCerradoSinScope(int $empresaId, int $anioReferencia): bool
    {
        return static::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('anio_referencia', $anioReferencia)
            ->where('estado', self::ESTADO_CERRADO)
            ->exists();
    }
}
