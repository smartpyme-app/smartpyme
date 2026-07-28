<?php

namespace App\Models\Comisiones;

use App\Models\Admin\Empresa;
use App\Models\User;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComisionLiquidacion extends Model
{
    protected $table = 'comision_liquidaciones';

    protected $fillable = [
        'id_empresa',
        'id_periodo',
        'id_vendedor',
        'total_comision',
        'pagado_at',
    ];

    protected $casts = [
        'total_comision' => 'decimal:4',
        'pagado_at' => 'datetime',
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

    public function periodo()
    {
        return $this->belongsTo(ComisionPeriodo::class, 'id_periodo');
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }
}
