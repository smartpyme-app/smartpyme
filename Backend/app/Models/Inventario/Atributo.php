<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Atributo extends Model
{

    protected $table = 'producto_atributo_valores';
    protected $fillable = array(
        'tipo',
        'valor',
        'id_empresa',
        'enable'
    );

    public $timestamps = true;

    protected $dates = [
        'created_at',
        'updated_at',

    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            $user = Auth::user();
            static::addGlobalScope('empresa', function (Builder $builder) use ($user) {
                $builder->where('id_empresa', $user->id_empresa);
            });
        }
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
}
