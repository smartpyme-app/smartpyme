<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'id_empresa',
        'enable',
        'id_sucursal',
        'tipo',
        'modulo_citas',
        'codigo_autorizacion',
        'editar_precio_venta'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'enable' => 'boolean'
    ];

    public function empresa(){
        return $this->belongsTo('App\Models\Empresa', 'id_empresa');
    }

    public function getJWTIdentifier() {
      return $this->getKey();
    }

    public function getJWTCustomClaims() {
      return [];
    }

}
