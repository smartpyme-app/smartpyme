<?php

namespace App\Models\Ventas\Orden_Produccion;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Admin\Notificacion;
use App\Models\Admin\Sucursal;
use App\Models\CotizacionVenta;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrdenProduccion extends Model
{
    protected $table = 'ordenes_produccion';

    protected $fillable = [
        'codigo',
        'fecha',
        'fecha_entrega',
        'estado',
        'id_cotizacion_venta',
        'id_cliente',
        'id_usuario',
        'id_asesor',
        'observaciones',
        'subtotal',
        'total_costo',
        'descuento',
        'no_sujeta',
        'excenta',
        'cuenta_a_terceros',
        'gravada',
        'iva',
        'total',
        'id_empresa',
        'id_bodega',
        'terminos_condiciones',
        'id_vendedor',
        'cantidad_producida',
    ];
    protected $appends = ['nombre_cliente', 'nombre_usuario', 'nombre_vendedor',  'nombre_sucursal', 'nombre_documento','avance','has_documento'];


    protected $casts = [
        'fecha' => 'date',
        'fecha_entrega' => 'date',
        'subtotal' => 'decimal:2',
        'total_costo' => 'decimal:2',
        'descuento' => 'decimal:2',
        'no_sujeta' => 'decimal:2',
        'excenta' => 'decimal:2',
        'cuenta_a_terceros' => 'decimal:2',
        'gravada' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }


        self::created(function ($model) {
            self::crearNotificacion($model, [
                'titulo' => 'Nueva Orden de Producción',
                'descripcion' => "Nueva orden de producción #{$model->codigo} creada"
            ]);
        });


        self::updated(function ($model) {
            if ($model->isDirty('estado') && $model->estado === 'completada') {
                self::crearNotificacion($model, [
                    'titulo' => 'Orden de Producción Completada',
                    'descripcion' => "La orden de producción #{$model->codigo} completada"
                ]);
            }
        });
    }


    private static function crearNotificacion($model, array $datos)
    {
        Notificacion::create([
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'tipo' => 'Orden de Producción',
            'categoria' => 'ordenes_produccion',
            'prioridad' => 'Alta',
            'leido' => false,
            'referencia' => 'orden-produccion/detalles',
            'id_referencia' => $model->id,
            'id_empresa' => $model->id_empresa,
            'id_sucursal' => null,
            'id_producto' => null,
            'dashboard' => true,
            'tipo_referencia' => 'orden_produccion',
            'id_orden_produccion' => $model->id
        ]);
    }

    public function getNombreClienteAttribute()
    {
        if (!$this->relationLoaded('cliente')) {
            $cliente = $this->cliente()->first();
        } else {
            $cliente = $this->cliente;
        }
        
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return 'Consumidor Final';
    }

    public function getDteAttribute($value)
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function getDteInvalidacionAttribute($value)
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function getNombreUsuarioAttribute()
    {
        if (!$this->relationLoaded('usuario')) {
            return $this->usuario()->pluck('name')->first();
        }
        return $this->usuario ? $this->usuario->name : null;
    }

    public function getNombreVendedorAttribute()
    {
        if (!$this->relationLoaded('vendedor')) {
            return $this->vendedor()->pluck('name')->first();
        }
        return $this->vendedor ? $this->vendedor->name : null;
    }

    public function getNombreSucursalAttribute()
    {
        if (!$this->relationLoaded('sucursal')) {
            return $this->sucursal()->pluck('nombre')->first();
        }
        return $this->sucursal ? $this->sucursal->nombre : null;
    }

    public function getNombreDocumentoAttribute()
    {
        if (!$this->relationLoaded('documento')) {
            return $this->documento()->pluck('nombre')->first();
        }
        return $this->documento ? $this->documento->nombre : null;
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'id_documento');
    }

    public function detalleText()
    {
        $text = '';

        foreach ($this->detalles as $detalle) {
            $text .= $detalle->nombre_producto . ' X ' . $detalle->cantidad . '. ';
            if ($detalle->producto()->first()->promocion()->first()) {
                foreach ($detalle->producto()->first()->promocion()->first()->detalles()->get() as $det) {
                    $text .= ' - ' . $det->nombre_producto . ' X ' . $det->cantidad . '. ';
                }
            }
        }

        return $text;
    }

    public function getSaldoAttribute()
    {
        return round($this->total - $this->abonos()->where('estado', 'Confirmado')->sum('total'), 2);
    }

    public function getAvanceAttribute()
    {
    
        if ($this->detalles->isEmpty()) {
            return 0;
        }

        $total_producido = 0;
        $total_solicitado = 0;

        foreach ($this->detalles as $detalle) {
            $total_producido += $detalle->cantidad_producida ?? 0;
            $total_solicitado += $detalle->cantidad;
        }

        // Evitar división por cero
        if ($total_solicitado === 0) {
            return 0;
        }

        // Calcular el porcentaje y redondear a 2 decimales
        return round(($total_producido / $total_solicitado) * 100, 2);
    }

    

    // Relaciones
    public function detalles()
    {
        return $this->hasMany(DetalleOrdenProduccion::class, 'id_orden_produccion');
    }

    public function historial()
    {
        return $this->hasMany(HistorialOrdenProduccion::class, 'id_orden_produccion');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function asesor()
    {
        return $this->belongsTo(User::class, 'id_asesor');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function cotizacion()
    {
        return $this->belongsTo(CotizacionVenta::class, 'id_cotizacion_venta');
    }
    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }

    //documento
    public function documentoOrden()
    {
        return $this->hasOne(OrdenProduccionDocumento::class, 'id_orden_produccion');
    }

    //has documento
    public function getHasDocumentoAttribute()
    {
        return $this->documentoOrden()->exists();
    }

    /**
     * Scope para cargar todas las relaciones necesarias para los accessors
     * Evita N+1 queries cuando se listan múltiples órdenes de producción
     */
    public function scopeWithAccessorRelations($query)
    {
        return $query->with([
            'cliente',
            'usuario',
            'vendedor',
            'sucursal',
            'documento'
        ]);
    }
}
