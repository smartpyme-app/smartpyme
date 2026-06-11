<?php

namespace App\Models\Ventas\Clientes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DireccionEnvio extends Model
{
    protected $table = 'direcciones_envio';

    protected $fillable = [
        'id_cliente',
        'id_empresa',
        'alias',
        'direccion',
        'referencia',
        'telefono',
        'codigo_area',
        'latitud',
        'longitud',
        'boxful_state_id',
        'boxful_city_id',
        'boxful_address_id',
        'es_predeterminada'
    ];

    protected $casts = [
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
        'es_predeterminada' => 'boolean',
        'boxful_state_id' => 'string',
        'boxful_city_id' => 'string'
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('empresa', function (Builder $builder) {
            $user = Auth::guard('api')->user() ?? Auth::user();
            if (!$user) {
                return;
            }

            $empresa = $user->empresa;

            if ($empresa) {
                if ($empresa->esEmpresaPadre()) {
                    // Empresa padre: solo ve sus propios registros
                    $builder->where('direcciones_envio.id_empresa', $user->id_empresa);
                } elseif ($empresa->esEmpresaHija()) {
                    // Empresa hija: ve registros de todas las empresas hijas
                    $empresaPadre = $empresa->getEmpresaPadre();
                    if ($empresaPadre && $empresaPadre->licencia) {
                        $empresasHijasIds = $empresaPadre->licencia->empresas->pluck('id_empresa')->toArray();
                        $builder->whereIn('direcciones_envio.id_empresa', $empresasHijasIds);
                    } else {
                        // Fallback: solo sus propios registros
                        $builder->where('direcciones_envio.id_empresa', $user->id_empresa);
                    }
                } else {
                    // Empresa normal sin licencia
                    $builder->where('direcciones_envio.id_empresa', $user->id_empresa);
                }
            }
        });
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
