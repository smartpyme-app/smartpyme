<?php

namespace App\Models\Audit;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Models\Audit as BaseAudit;

class Audit extends BaseAudit
{
    protected static function booted(): void
    {
        // ponytail: mismo patrón que Venta; el scope se registra siempre y Auth se evalúa al consultar.
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
