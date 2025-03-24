<?php

namespace App\Models\Compras\Retaceo;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Compras\Compra;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Retaceo extends Model
{
    use HasFactory;

    protected $table = 'retaceos';

    protected $fillable = [
        'codigo',
        'id_compra',
        'numero_duca',
        'tasa_dai',
        'numero_factura',
        'incoterm',
        'fecha',
        'estado',
        'observaciones',
        'total_gastos',
        'total_retaceado',
        'id_empresa',
        'id_sucursal',
        'id_usuario'
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
    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra');
    }

    /**
     * Relación con los gastos
     */
    public function gastos()
    {
        return $this->hasMany(RetaceoGasto::class, 'id_retaceo');
    }

    /**
     * Relación con la distribución
     */
    public function distribucion()
    {
        return $this->hasMany(RetaceoDistribucion::class, 'id_retaceo');
    }

    /**
     * Relación con la empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Relación con la sucursal
     */
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    /**
     * Relación con el usuario
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}