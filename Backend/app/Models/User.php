<?php

namespace App\Models;

use App\Models\Authorization\AuthorizationType;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    use HasRoles;


    protected $fillable = [
        'name',
        'email',
        'password',
        'id_empresa',
        'enable',
        'tour_bienvenida',
        'codigo',
        'id_bodega',
        'id_sucursal',
        'id_authorization',
        'tipo',
        'modulo_citas',
        'codigo_autorizacion',
        'pending_changes',
        'editar_precio_venta',
    ];

    protected $hidden = ['password', 'remember_token','rol_id'];
    // protected $appends = ['nombre_sucursal'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'enable' => 'string',
        'pending_changes' => 'array',
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
        
        // Verificar que el email del usuario sea válido antes de enviar
        if (!$this->email || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            \Log::warning("No se puede enviar email de bienvenida: email inválido para usuario {$this->id}");
            return;
        }
        
        // Obtener dirección de correo válida para el remitente
        $fromAddress = env('MAIL_FROM_ADDRESS');
        if (!$fromAddress || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $fromAddress = 'noreply@smartpyme.site'; // Fallback por defecto
        }
        
        Mail::send('mails.bienvenida-usuario', ['usuario' => $usuario ], function ($m) use ($usuario, $fromAddress) {
            $m->from($fromAddress, 'SmartPyme')
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
    

    //solo se puede tener un rol    
    public function getRoleAttribute(){
        return $this->roles->first();
    }

    public function getRolIdAttribute(){
        $firstRole = $this->roles->first();
        return $firstRole ? $firstRole->id : null;
    }

    public function authorizationTypes()
    {
        return $this->belongsToMany(AuthorizationType::class, 'user_authorization_types', 'user_id', 'authorization_type_id');
    }

    public function authorization()
    {
        return $this->belongsTo('App\Models\Authorization\Authorization', 'id_authorization');
    }

    public function role()
    {
        return $this->tipo;
    }


}
