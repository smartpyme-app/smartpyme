<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Auth;
use Mail;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'id_empresa',
        'enable',
        'tour_bienvenida',
        'codigo',
        'id_sucursal',
        'tipo',
        'modulo_citas',
        'codigo_autorizacion',
        'editar_precio_venta'
    ];

    protected $hidden = ['password', 'remember_token'];
    // protected $appends = ['nombre_sucursal'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'enable' => 'string'
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

    public function bienvenida(){
        $usuario = User::where('id', $this->id)->with('empresa')->first();
        Mail::send('mails.bienvenida-usuario', ['usuario' => $usuario ], function ($m) use ($usuario) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
            ->to($this->email)
            ->subject('¡Bienvenido a SmartPyme!');
        });
    }

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function accesos(){
        return $this->hasMany('App\Models\Admin\Acceso', 'id_usuario');
    }

    public function getJWTIdentifier() {
      return $this->getKey();
    }

    public function getJWTCustomClaims() {
      return [];
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'usuario_id');
    }

}
