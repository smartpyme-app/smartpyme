<?php

namespace App\Models\Comisiones;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\SubCategoria;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComisionSubcategoriaConfig extends Model
{
    protected $table = 'comision_subcategoria_config';

    protected $fillable = [
        'id_empresa',
        'id_subcategoria',
        'porcentaje',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:4',
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

    public function subcategoria()
    {
        return $this->belongsTo(SubCategoria::class, 'id_subcategoria');
    }
}
