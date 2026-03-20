<?php

namespace App\Models\Ventas\Clientes;

use App\Models\FidelizacionClientes\ConsumoPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\MH\ActividadEconomica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model {

    // use SoftDeletes;
    protected $table = 'clientes';
    protected $fillable = [
       'nombre',
       'apellido',
       'ncr',
       'giro',
       'tipo',
       'tipo_contribuyente',
       'clasificacion',
       'dui',
       'nit',
       'nombre_empresa',
       'empresa_telefono',
       'empresa_direccion',
       'direccion',
       'pais',
       'cod_pais',
       'municipio',
       'distrito',
       'departamento',
       'fecha_cumpleanos',
       'telefono',
       'correo',
       'shopify_customer_id',
       'nota',
       'red_social',
       'enable',
       'etiquetas',
       'id_usuario',
       'id_empresa',
       'nivel',
       'id_tipo_cliente',
       'id_vendedor',
       'habilita_credito',
       'dias_credito',
       'limite_credito',

       'cod_giro',
       'cod_municipio',
       'cod_distrito',
       'cod_departamento',
       'tipo_persona',
       'tipo_documento',
       'codigo_cliente',

    ];
    protected $appends = ['nombre_completo'];
    protected $casts = [
        'enable' => 'boolean',
        'habilita_credito' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

    if (Auth::check()) {
        static::addGlobalScope('empresa', function (Builder $builder) {
            $user = Auth::user();
            $empresa = $user->empresa;

            if ($empresa) {
                if ($empresa->esEmpresaPadre()) {
                    // Empresa padre: solo ve sus propios clientes
                    $builder->where('clientes.id_empresa', $user->id_empresa);
                } elseif ($empresa->esEmpresaHija()) {
                    // Empresa hija: ve clientes de todas las empresas hijas (sin incluir empresa padre)
                    $empresaPadre = $empresa->getEmpresaPadre();
                    if ($empresaPadre && $empresaPadre->licencia) {
                        $empresasHijasIds = $empresaPadre->licencia->empresas->pluck('id_empresa')->toArray();
                        $builder->whereIn('clientes.id_empresa', $empresasHijasIds);
                    } else {
                        // Fallback: solo sus propios clientes
                        $builder->where('clientes.id_empresa', $user->id_empresa);
                    }
                } else {
                    // Empresa normal sin licencia
                    $builder->where('clientes.id_empresa', $user->id_empresa);
                }
            }


        });
    }

    }

    public function getNombreCompletoAttribute()
    {
        return $this->nombre . ' ' . ($this->apellido ? $this->apellido : '');
    }

    public function getNombreActividadEconomicaAttribute()
    {
        return $this->actividadEconomica ? $this->actividadEconomica->nombre : null;
    }

    public function getEtiquetasAttribute($value)
    {
        return is_string($value) ? json_decode($value) : $value;
    }

    public function setEtiquetasAttribute($valor)
    {
        $this->attributes['etiquetas'] = json_encode($valor);
    }

    public function cotizaciones()
    {
        return $this->hasMany('App\Models\Cotizaciones\Cotizacion', 'id_cliente');
    }

    public function eventos()
    {
        return $this->hasMany('App\Models\Eventos\Evento', 'id_cliente');
    }

    public function ventas()
    {
        return $this->hasMany('App\Models\Ventas\Venta', 'id_cliente');
    }

    public function paquetes()
    {
        return $this->hasMany('App\Models\Inventario\Paquete', 'id_cliente');
    }

    public function creditos()
    {
        return $this->hasMany('App\Models\Creditos\Credito', 'id_cliente');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function contactos()
    {
        return $this->hasMany(ContactoCliente::class, 'id_cliente')
                    ->where('estado', 1);
    }

    public function actividadEconomica()
    {
        return $this->belongsTo(ActividadEconomica::class, 'cod_giro', 'cod');
    }

    public function tipoCliente()
    {
        return $this->belongsTo(TipoClienteEmpresa::class, 'id_tipo_cliente');
    }

    public function puntosCliente()
    {
        return $this->hasOne(PuntosCliente::class, 'id_cliente');
    }

    public function transaccionesPuntos()
    {
        return $this->hasMany(TransaccionPuntos::class, 'id_cliente');
    }

    public function consumosPuntos()
    {
        return $this->hasMany(ConsumoPuntos::class, 'id_cliente');
    }

    public function getTipoClienteEfectivo()
    {
        if ($this->tipoCliente) {
            return $this->tipoCliente;
        }

        // Si la empresa tiene licencia, usar la configuración de la empresa padre
        $empresaEfectiva = $this->empresa;
        if ($empresaEfectiva && $empresaEfectiva->esEmpresaHija()) {
            $empresaEfectiva = $empresaEfectiva->getEmpresaPadre();
        }

        return $empresaEfectiva ? $empresaEfectiva->tipoClienteDefault : null;
    }

    public function getPuntosDisponibles()
    {
        return $this->puntosCliente->puntos_disponibles ?? 0;
    }

    public function getPuntosTotalesGanados()
    {
        return $this->puntosCliente->puntos_totales_ganados ?? 0;
    }

    public function getPuntosTotalesCanjeados()
    {
        return $this->puntosCliente->puntos_totales_canjeados ?? 0;
    }

    public function getUltimaActividad()
    {
        return $this->puntosCliente->fecha_ultima_actividad;
    }

    public function isVip()
    {
        $tipo = $this->getTipoClienteEfectivo();
        return $tipo && in_array($tipo->tipoBase->code ?? '', ['VIP', 'ULTRAVIP']);
    }

    public function isUltraVip()
    {
        $tipo = $this->getTipoClienteEfectivo();
        return $tipo && ($tipo->tipoBase->code ?? '') === 'ULTRAVIP';
    }

    public function getTelefonoEfectivo()
    {
        return $this->tipo === 'Empresa' ? $this->empresa_telefono : $this->telefono;
    }

    public function getDireccionEfectiva()
    {
        return $this->tipo === 'Empresa' ? $this->empresa_direccion : $this->direccion;
    }

    public function vendedor()
    {
        return $this->belongsTo('App\Models\User', 'id_vendedor');
    }

    public function distritoRelation()
    {
        return $this->belongsTo(\App\Models\MH\Distrito::class, 'cod_distrito', 'cod');
    }
}
