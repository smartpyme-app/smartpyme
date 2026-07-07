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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
