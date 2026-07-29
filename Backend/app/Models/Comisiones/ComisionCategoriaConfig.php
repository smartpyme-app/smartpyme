<?php

namespace App\Models\Comisiones;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComisionCategoriaConfig extends Model
{
    protected $table = 'comision_categoria_config';

    protected $fillable = [
        'id_empresa',
        'id_categoria',
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

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }
}
