<?php

namespace App\Models;

use App\Models\Authorization\AuthorizationType;
use App\Models\MetodoPago;
use App\Models\Suscripcion;
use App\Models\OrdenPago;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Traits\HasRoles;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    use HasRoles;

    /**
     * Roles y permisos viven solo con guard 'web'. Sin esto, el middleware
     * permission: falla en rutas API porque auth:api cambia auth.defaults.guard
     * a 'api' y Spatie busca los permisos bajo ese guard.
     */
    protected $guard_name = 'web';


    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

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
        'id_canal',
        'tipo',
        'modulo_citas',
        'codigo_autorizacion',
        'editar_precio_venta',
        'woocommerce_status',
        'telefono',
        'whatsapp_verification_code',
        'whatsapp_code_expires_at',
        'whatsapp_verified',
        'pending_changes',
        'shopify_status',

    ];

    protected $hidden = ['password', 'remember_token', 'whatsapp_verification_code','rol_id'];
    // protected $appends = ['nombre_sucursal'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'enable' => 'string',
        'pending_changes' => 'array',
        'whatsapp_code_expires_at' => 'datetime',
        'whatsapp_verified' => 'boolean',
    ];

    public function syncTipoFromRole($roleId = null)
    {
        $role = $roleId ? \Spatie\Permission\Models\Role::find($roleId) : $this->roles()->first();
        if ($role) {
            if ($role->name === config('constants.ROL_SUPER_ADMIN', 'super_admin')) {
                $this->tipo = 'Super Administrador';
            } elseif ($role->name === config('constants.ROL_ADMIN', 'admin')) {
                $this->tipo = config('constants.TIPO_USUARIO_ADMINISTRADOR', 'Administrador');
            } elseif (in_array($role->name, [config('constants.ROL_USUARIO_VENDEDOR', 'usuario_vendedor'), 'usuario_ventas', 'vendedor'])) {
                $this->tipo = config('constants.TIPO_USUARIO_VENDEDOR', 'Vendedor');
            } else {
                $this->tipo = ucfirst(str_replace('_', ' ', $role->name));
            }
            $this->save();
        }
    }

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
        try {
            $usuario = User::where('id', $this->id)->with('empresa')->first();
            $fromAddress = config('mail.from.address') ?: (env('MAIL_FROM_ADDRESS') ?: 'no-reply@smartpyme.app');
            Mail::send('mails.bienvenida-usuario', ['usuario' => $usuario], function ($m) use ($fromAddress) {
                $m->from($fromAddress, 'SmartPyme')
                    ->to($this->email)
                    ->subject('¡Bienvenido a SmartPyme!');
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al enviar correo de bienvenida: ' . $e->getMessage());
        }
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

    public function bodega()
    {
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function canal()
    {
        return $this->belongsTo('App\Models\Admin\Canal', 'id_canal');
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

        if ($this->tipo === 'vendedor' || $this->tipo === 'Ventas' || $this->tipo === 'Ventas Limitado') {
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

    //solo se puede tener un rol
    public function getRoleAttribute(){
        return $this->roles->first();
    }

    public function getRolIdAttribute(){
        return optional($this->roles->first())->id;
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
