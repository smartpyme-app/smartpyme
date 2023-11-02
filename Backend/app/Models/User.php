<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use JWTAuth;

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
    protected $appends = ['nombre_sucursal'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'enable' => 'string'
    ];

    // protected static function booted()
    // {
    //     $usuario = JWTAuth::parseToken()->authenticate();

    //     if ($usuario){
    //         static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
    //             $builder->where('id_empresa', $usuario->id_empresa);
    //         });
    //     }
        
    // }

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function getJWTIdentifier() {
      return $this->getKey();
    }

    public function getJWTCustomClaims() {
      return [];
    }

}
