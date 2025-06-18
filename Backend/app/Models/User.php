<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
        'id_bodega',
        'id_sucursal',
        'tipo',
        'modulo_citas',
        'codigo_autorizacion',
        'editar_precio_venta',
        'woocommerce_status',
        'telefono',
        'whatsapp_verification_code',
        'whatsapp_code_expires_at',
        'whatsapp_verified'
    ];

    protected $hidden = ['password', 'remember_token', 'whatsapp_verification_code'];
    // protected $appends = ['nombre_sucursal'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'enable' => 'string',
        'whatsapp_code_expires_at' => 'datetime',
        'whatsapp_verified' => 'boolean'
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

    public function bienvenida()
    {
        $usuario = User::where('id', $this->id)->with('empresa')->first();
        Mail::send('mails.bienvenida-usuario', ['usuario' => $usuario], function ($m) use ($usuario) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                ->to($this->email)
                ->subject('¡Bienvenido a SmartPyme!');
        });
    }

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function accesos()
    {
        return $this->hasMany('App\Models\Admin\Acceso', 'id_usuario');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'usuario_id');
    }

    public function ordenesPago()
    {
        return $this->hasMany(OrdenPago::class, 'id_usuario');
    }

    public function metodoPago()
    {
        return $this->hasMany(MetodoPago::class, 'id_usuario');
    }

    public function whatsappSession()
    {
        return $this->hasOne('App\Models\WhatsApp\WhatsAppSession', 'id_usuario');
    }

    public function whatsappMessages()
    {
        return $this->hasMany('App\Models\WhatsApp\WhatsAppMessage', 'id_usuario');
    }

    public function hasActiveWhatsAppSession()
    {
        return $this->whatsappSession()
            ->where('status', 'connected')
            ->where('last_message_at', '>=', now()->subHours(24))
            ->exists();
    }

    public function getWhatsAppPermissions()
    {
        $permissions = [
            'view_sales' => false,
            'view_inventory' => false,
            'view_customers' => false,
            'view_reports' => false,
            'view_company_data' => false
        ];


        if ($this->tipo === 'Administrador' || $this->tipo === 'admin') {
            return array_map(fn() => true, $permissions);
        }

        if ($this->tipo === 'vendedor') {
            $permissions['view_sales'] = true;
            $permissions['view_inventory'] = true;
            $permissions['view_customers'] = true;
        }

        return $permissions;
    }

    public function canAccessWhatsAppData($dataType)
    {
        $permissions = $this->getWhatsAppPermissions();
        return $permissions[$dataType] ?? false;
    }
}
