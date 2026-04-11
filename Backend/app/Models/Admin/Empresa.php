<?php

namespace App\Models\Admin;

use App\Models\Currency;
use App\Models\FidelizacionClientes\ConsumoPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\Planilla\CargoEmpresa;
use App\Models\Planilla\DepartamentoEmpresa;
use App\Models\Suscripcion;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Empresa extends Model
{

    // use SoftDeletes;
    protected $table = 'empresas';
    protected $fillable = [
        'nombre',
        'codigo',
        'nombre_propietario',
        'sector',
        'giro',
        'nit',
        'ncr',
        'tipo_contribuyente',
        'departamento',
        'municipio',
        'distrito',
        'direccion',
        'telefono',
        'correo',
        'municipio',
        'departamento',
        'logo',
        'propina',
        'propina_porcentaje',
        'monto_minimo_retencion_iva_gc',
        'valor_inventario',
        'vender_sin_stock',
        'user_limit',
        'sucursal_limit',
        'iva',
        'moneda',
        'pais',
        'cod_pais',
        'total',
        'frecuencia_pago',
        'monto_mensual',
        'monto_anual',
        'forma_pago',
        'link_pago',
        'fecha_ultimo_pago',
        'editar_precio_venta',
        'agrupar_detalles_venta',
        'editar_descripcion_venta',
        'impresion_en_facturacion',
        'vendedor_detalle_venta',
        'cambiar_tipo_impuesto_venta',
        'vendedor_asignado',
        'venta_consigna',
        'plan',
        'cobra_iva',
        'tipo_plan',
        'fecha_cancelacion',
        'metodo_pago',
        'pago_recurrente',
        'referido',
        'campania',
        'codigo_promocional',
        'wompi_aplicativo',
        'wompi_id',
        'wompi_secret',
        'modulo_paquetes',
        'webhook_paquete_venta_enabled',
        'webhook_paquete_venta_url',
        'webhook_paquete_venta_secret',
        'webhook_paquete_venta_bearer_token',
        'modulo_citas',
        'modulo_proyectos',
        'activo',
        'alerta_suscripcion',
        'cotizacion_compras_terminos',

        //Facturacíon
        'facturacion_electronica',
        'enviar_dte',
        'fe_ambiente',
        'cod_municipio',
        'cod_departamento',
        'cod_distrito',
        'cod_actividad_economica',
        'tipo_establecimiento',
        'mh_pwd_certificado',
        'mh_usuario',
        'mh_contrasena',
        'cod_estable_mh',
        'cod_estable',

        //Permiso para vendedores
        'vendedor_inventario',

        //Para facturación
        'id_cliente',
        'id_documento',
        'woocommerce_api_key',
        'woocommerce_store_url',
        'woocommerce_consumer_key',
        'woocommerce_consumer_secret',
        'woocommerce_status',
        'woocommerce_sync_progress',
        'woocommerce_sync_total_batches',
        'woocommerce_sync_processed_batches',
        'woocommerce_sync_status',
        'woocommerce_last_sync',
        'woocommerce_error',
        'woocommerce_canal_id',

        //Personalización
        'custom_empresa',

        //Renta
        'tipo_renta_servicios',
        'tipo_renta_productos',
        'tipo_sector',

        //Sello y firma
        'sello',
        'firma',
        'mostrar_sello_firma',
        'mostrar_sello_firma_cotizacion',
        'shopify_store_url',
        'shopify_consumer_secret',
        'shopify_webhook_secret',
        'shopify_status',
        'shopify_canal_id',
        'shopify_sync_progress',
        'shopify_sync_total_batches',
        'shopify_sync_processed_batches',
        'shopify_sync_status',
        'shopify_sync_bidirectional',
        'shopify_last_sync',
        'shopify_error',
        'importacion_productos_shopify',

    ];

    protected $casts = [
        'monto_minimo_retencion_iva_gc' => 'decimal:2',
        'enviar_dte' => 'boolean',
        'facturacion_electronica' => 'boolean',
        'custom_empresa' => 'json',
        'importacion_productos_shopify' => 'boolean',
        'shopify_sync_bidirectional' => 'boolean',
        'webhook_paquete_venta_enabled' => 'boolean',
    ];

    protected $appends = [
        'estado_plan',
        'woocommerce_api_url',
        'status_conexion_woocommerce',
        'is_current_user_connected_to_woocommerce',
        'currency_symbol',
        'acces_chatbot_whatsapp',
        'generar_partidas',
        'shopify_webhook_url',
        'status_conexion_shopify',
        'is_current_user_connected_to_shopify'
    ];

    public function limiteUsuarios()
    {
        if ($this->usuarios->where('enable', true)->count() < $this->user_limit)
            return false;
        return true;
    }

    public function limiteSucursales()
    {
        if ($this->sucursales->where('activo', true)->count() < $this->sucursal_limit)
            return false;
        return true;
    }

    public function getEstadoPlanAttribute()
    {
        if (!$this->created_at) {
            return ['estado' => 'Sin fecha de creación', 'dias_faltantes' => null];
        }

        $pago_mes = $this->pagos()->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'))->count();

        $dias_creaccion = $this->created_at->diffInDays(Carbon::now());

        if ($dias_creaccion <= 15) {
            return ['estado' => 'Prueba', 'dias_faltantes' => (15 - $dias_creaccion)];
        }
        if ($dias_creaccion > 15) {
            return ['estado' => 'Pendiente de pago'];
        }

        return $this->pagos->count();
    }

    public function getGenerarPartidasAttribute(){
        return $this->contabilidad()->pluck('generar_partidas')->first();
    }


    public function contabilidad(){
        return $this->hasOne('App\Models\Contabilidad\Configuracion', 'id_empresa');
    }

    public function usuarios()
    {
        return $this->hasMany('App\Models\User', 'id_empresa');
    }

    public function ventas()
    {
        return $this->hasMany('App\Models\Ventas\Venta', 'id_empresa');
    }

    public function proveedores()
    {
        return $this->hasMany('App\Models\Compras\Proveedores\Proveedor', 'id_empresa');
    }

    public function documentos()
    {
        return $this->hasMany('App\Models\Admin\Documento', 'id_empresa');
    }

    public function formasDePago()
    {
        return $this->hasMany('App\Models\Admin\FormasDePago', 'id_empresa');
    }

    public function clientes()
    {
        return $this->hasMany('App\Models\Ventas\Clientes\Cliente', 'id_empresa');
    }

    public function productos()
    {
        return $this->hasMany('App\Models\Inventario\Producto', 'id_empresa');
    }

    public function licencia()
    {
        return $this->hasOne('App\Models\Licencias\Licencia', 'id_empresa');
    }

    public function licenciaEmpresa()
    {
        return $this->hasOne('App\Models\Licencias\Empresa', 'id_empresa');
    }

    public function empresasHijas()
    {
        if ($this->licencia) {
            return $this->licencia->empresas()->where('id_empresa', '!=', $this->id);
        }
        return collect();
    }

    public function esEmpresaPadre()
    {
        $tieneLicencia = $this->licencia()->exists();
        return $tieneLicencia;
    }

    public function esEmpresaHija()
    {
        $esHija = $this->licenciaEmpresa()->exists();
        return $esHija;
    }

    public function getEmpresaPadre()
    {
        if ($this->esEmpresaHija()) {
            $licenciaEmpresa = $this->licenciaEmpresa;
            if ($licenciaEmpresa && $licenciaEmpresa->licencia) {
                return $licenciaEmpresa->licencia->empresa;
            }
        }
        return $this;
    }

    public function getEmpresasLicencia()
    {
        // Priorizar empresa padre sobre empresa hija si es ambas
        if ($this->esEmpresaPadre()) {
            $licencia = $this->licencia;
            if ($licencia) {
                // Incluir la empresa padre + todas las empresas hijas
                $empresasHijas = $licencia->empresas->pluck('empresa');
                $resultado = $empresasHijas->prepend($this); // Agregar la empresa padre al inicio
                
                return $resultado;
            }
        } elseif ($this->esEmpresaHija()) {
            $empresaPadre = $this->getEmpresaPadre();
            if ($empresaPadre && $empresaPadre->licencia) {
                // Incluir la empresa padre + todas las empresas hijas
                $empresasHijas = $empresaPadre->licencia->empresas->pluck('empresa');
                $resultado = $empresasHijas->prepend($empresaPadre); // Agregar la empresa padre al inicio

                return $resultado;
            }
        }
        return collect([$this]);
    }

    public function getEmpresasLicenciaIds()
    {
        return $this->getEmpresasLicencia()->pluck('id')->toArray();
    }

    public function dashboards()
    {
        return $this->hasMany('App\Models\Admin\Dashboard', 'id_empresa');
    }

    public function gastos()
    {
        return $this->hasMany('App\Models\Compras\Gastos\Gasto', 'id_empresa');
    }

    public function compras()
    {
        return $this->hasMany('App\Models\Compras\Compra', 'id_empresa');
    }

    public function canales()
    {
        return $this->hasMany('App\Models\Admin\Canal', 'id_empresa');
    }

    public function bodegas()
    {
        return $this->hasMany('App\Models\Inventario\Bodega', 'id_empresa');
    }

    public function sucursales()
    {
        return $this->hasMany('App\Models\Admin\Sucursal', 'id_empresa');
    }

    public function deventas()
    {
        return $this->hasMany('App\Models\Ventas\Devoluciones\Devolucion', 'id_empresa');
    }
    public function decompras()
    {
        return $this->hasMany('App\Models\Compras\Devoluciones\Devolucion', 'id_empresa');
    }

    public function recordatorios()
    {
        return $this->hasMany('App\Models\Admin\Notification', 'id_empresa');
    }

    public function ajustes()
    {
        return $this->hasMany('App\Models\Inventario\Ajuste', 'id_empresa');
    }

    public function impuestos()
    {
        return $this->hasMany('App\Models\Admin\Impuesto', 'id_empresa');
    }

    public function traslados()
    {
        return $this->hasMany('App\Models\Inventario\Traslado', 'id_empresa');
    }

    public function presupuestos()
    {
        return $this->hasMany('App\Models\Contabilidad\Presupuesto', 'id_empresa');
    }

    public function categorias()
    {
        return $this->hasMany('App\Models\Inventario\Categorias\Categoria', 'id_empresa');
    }

    public function pagos()
    {
        return $this->hasMany('App\Models\Transaccion', 'id_empresa');
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'empresa_id');
    }

    public function suscripcionActiva()
    {
        return $this->suscripciones()->where('estado', 'activo')->latest()->first();
    }

    public function suscripcionActivaCommand()
    {
        return $this->suscripciones()->latest()->first();
    }

    public function scopeConSuscripcionActiva($query)
    {
        return $query->whereHas('suscripciones', function ($subQuery) {
            $subQuery->where('estado', 'activo');
        });
    }

    public function tieneSuscripcionActiva(): bool
    {
        return $this->suscripciones()->where('estado', 'activo')->exists();
    }

    public function diasFaltantesSuscripcion(): ?int
    {
        $suscripcionActiva = $this->suscripcionActiva;

        if (!$suscripcionActiva) {
            return null;
        }

        return $suscripcionActiva->diasFaltantes();
    }

    public function whatsappSessions()
    {
        return $this->hasMany('App\Models\WhatsApp\WhatsAppSession', 'id_empresa');
    }

    public function whatsappMessages()
    {
        return $this->hasMany('App\Models\WhatsApp\WhatsAppMessage', 'id_empresa');
    }


    public function currency()
    {
        return $this->belongsTo(Currency::class, 'moneda', 'currency_code');
    }

    public function getRecibosPendientesAttribute()
    {
        return $this->pagos()->where('estado', 'Pendiente')->count();
    }

    public function getLastPayAttribute()
    {
        return $this->pagos()->pluck('created_at')->last();
    }

    public function getNextPayAttribute()
    {

        $next_pay = $this->pagos()->pluck('created_at')->last();
        if ($this->pagos()->count())
            $next_pay->addMonth(1);

        return $next_pay;
    }

    public function getLeidosAttribute()
    {
        $re = $this->recordatorios()->where('leido', false)->get();
        return $re->count();
    }

    public function suscripcion()
    {
        return $this->hasOne(Suscripcion::class, 'empresa_id');
    }

    public function departamentos()
    {
        return $this->belongsToMany(DepartamentoEmpresa::class, 'empresa_departamento')
            ->withPivot('estado')
            ->withTimestamps();
    }

    public function cargos()
    {
        return $this->belongsToMany(CargoEmpresa::class, 'empresa_cargo')
            ->withPivot('estado')
            ->withTimestamps();
    }


    public function user()
    {

        $user = Auth::user();
        return $user;
    }

    public function getWooCommerceApiUrlAttribute()
    {
        if (empty($this->woocommerce_api_key)) {
            return null;
        }

        return url('/api/webhook/woocommerce/' . $this->woocommerce_api_key);
    }
    public function getStatusConexionWoocommerceAttribute()
    {
        $connected_users = $this->usuarios->where('woocommerce_status', 'connected');

        if ($connected_users->count() > 0) {
            return 'connected';
        }

        return 'disconnected';
    }
    public function getIsCurrentUserConnectedToWooCommerceAttribute()
    {
        $current_user = Auth::user();
        return $current_user && $current_user->woocommerce_status === 'connected';
    }

    public function canal()
    {
        return $this->belongsTo('App\Models\Admin\Canal', 'woocommerce_canal_id');
    }

    public function tiposClienteEmpresa()
    {
        return $this->hasMany(TipoClienteEmpresa::class, 'id_empresa');
    }

    public function tipoClienteDefault()
    {
        return $this->hasOne(TipoClienteEmpresa::class, 'id_empresa')
                    ->where('is_default', true);
    }

    public function puntosCliente()
    {
        return $this->hasMany(PuntosCliente::class, 'id_empresa');
    }

    public function transaccionesPuntos()
    {
        return $this->hasMany(TransaccionPuntos::class, 'id_empresa');
    }

    public function consumosPuntos()
    {
        return $this->hasMany(ConsumoPuntos::class, 'id_empresa');
    }

    public function getCurrencySymbolAttribute()
    {
        return $this->currency ? $this->currency->currency_symbol : null;
    }

    public function inicializarEstadoPruebasMasivas()
    {
        $estadoPruebas = [
            'completado' => false,
            'fecha_completado' => null,
            'tipos' => [
                'facturas' => [
                    'requeridas' => 90,
                    'emitidas' => 0
                ],
                'creditosFiscales' => [
                    'requeridas' => 75,
                    'emitidas' => 0
                ],
                'notasCredito' => [
                    'requeridas' => 0,
                    'emitidas' => 0
                ],
                'notasDebito' => [
                    'requeridas' => 0,
                    'emitidas' => 0
                ],
                'facturasExportacion' => [
                    'requeridas' => 0,
                    'emitidas' => 0
                ],
                'sujetoExcluido' => [
                    'requeridas' => 0,
                    'emitidas' => 0
                ]
            ]
        ];

        // Actualizar el campo en la base de datos
        $this->fe_pruebas_estadisticas = $estadoPruebas;
        $this->save();

        return $estadoPruebas;
    }


    public function getCustomConfigAttribute()
    {
        if (empty($this->custom_empresa)) {
            return $this->initializeCustomConfig();
        }

        $config = $this->custom_empresa;
        // Asegurar que siempre sea array (evitar "Cannot use object of type stdClass as array")
        return $this->ensureConfigArray($config);
    }

    /**
     * Convierte config (array o stdClass) a array recursivamente.
     * @return array|mixed
     */
    protected function ensureConfigArray($config)
    {
        if (is_array($config)) {
            $result = [];
            foreach ($config as $key => $value) {
                $result[$key] = $this->ensureConfigArray($value);
            }
            return $result;
        }
        if ($config instanceof \stdClass) {
            return $this->ensureConfigArray((array) $config);
        }
        return $config;
    }

    public function initializeCustomConfig()
    {
        $defaultConfig = [
            'columnas' => [
                'columna_proyecto' => false
                // Para futuras columnas
            ],
            'modulos' => [],
            'configuraciones' => [
                'ticket_en_pdf' => false,
                'bloquear_cotizaciones_vendedores' => false,
                'dte_mostrar_descripcion_producto' => true,
            ],
            'campos_personalizados' => []
            // Para futuros campos personalizados
        ];

        $this->custom_empresa = $defaultConfig;
        $this->save();

        return $defaultConfig;
    }

    /**
     * Actualizar una configuración específica
     */
    public function updateCustomConfig($section, $key, $value)
    {
        $config = $this->custom_config;

        if (!isset($config[$section])) {
            $config[$section] = [];
        }

        $config[$section][$key] = $value;
        $this->custom_empresa = $config;
        $this->save();

        return $this;
    }

    /**
     * Obtener una configuración específica
     */
    public function getCustomConfigValue($section, $key = null, $default = null)
    {
        $config = $this->custom_config;

        if (!isset($config[$section])) {
            return $default;
        }

        if ($key === null) {
            return $config[$section];
        }

        return $config[$section][$key] ?? $default;
    }

    /**
     * Verificar si el módulo de lotes está activo para la empresa
     */
    public function isLotesActivo(): bool
    {
        return (bool) $this->getCustomConfigValue('configuraciones', 'lotes_activo', false);
    }

    /**
     * Verificar si el campo componente químico está habilitado para la empresa
     */
    public function isComponenteQuimicoHabilitado(): bool
    {
        return (bool) $this->getCustomConfigValue('configuraciones', 'componente_quimico_activo', false);
    }

    /**
     * Verificar si el módulo de bancos está activo para la empresa
     */
    public function isModuloBancos(): bool
    {
        return (bool) $this->getCustomConfigValue('configuraciones', 'modulo_bancos', false);
    }

    /**
     * Categorías de gasto personalizadas, departamentos y áreas (selector en gastos y menú).
     */
    public function isGastosCategoriasPersonalizadasHabilitadas(): bool
    {
        return (bool) $this->getCustomConfigValue('configuraciones', 'gastos_categorias_personalizadas', false);
    }

    /**
     * Obtener la metodología de lotes (FIFO, LIFO, FEFO, Manual)
     */
    public function getLotesMetodologia(): string
    {
        return $this->getCustomConfigValue('configuraciones', 'lotes_metodologia', 'FIFO') ?: 'FIFO';
    }

    /**
     * Verificar si una columna está habilitada
     */
    public function isColumnEnabled($columnName)
    {
        return $this->getCustomConfigValue('columnas', $columnName, false);
    }

    /**
     * Obtener el tipo de cliente por defecto para la empresa
     * 
     * @return TipoClienteEmpresa|null
     */
    // public function getTipoClienteDefault()
    // {
    //     return $this->tipoClienteDefault;
    // }

    /**
     * Verificar si la empresa tiene habilitado el módulo de fidelización
     * 
     * @return bool
     */
    public function tieneFidelizacionHabilitada()
    {
        return $this->hasMany(\App\Models\Admin\EmpresaFuncionalidad::class, 'id_empresa')
                    ->whereHas('funcionalidad', function($query) {
                        $query->where('slug', 'fidelizacion-clientes');
                    })
                    ->where('activo', true)
                    ->exists();
    }

    /**
     * Habilitar/deshabilitar una columna
     */
    public function toggleColumn($columnName, $enabled = null)
    {
        if ($enabled === null) {
            $enabled = !$this->isColumnEnabled($columnName);
        }

        return $this->updateCustomConfig('columnas', $columnName, $enabled);
    }

    /**
     * Agregar nueva configuración personalizada
     */
    public function addCustomConfigSection($section, $data = [])
    {
        $config = $this->custom_config;
        $config[$section] = $data;
        $this->custom_empresa = $config;
        $this->save();

        return $this;
    }

    /**
     * Obtener todas las columnas disponibles con su estado
     */
    public function getAvailableColumns()
    {
        return [
            'columna_proyecto' => [
                'label' => 'Columna Proyecto',
                'description' => 'Mostrar columna de proyectos en listados',
                'enabled' => $this->isColumnEnabled('columna_proyecto'),
                'section' => 'Proyectos'
            ],
            // Aquí puedes agregar más columnas fácilmente
            // 'columna_categoria' => [
            //     'label' => 'Columna Categoría',
            //     'description' => 'Mostrar columna de categorías en productos',
            //     'enabled' => $this->isColumnEnabled('columna_categoria'),
            //     'section' => 'Inventario'
            // ],
        ];
    }

    public function generateWhatsAppCode()
    {
        if (!$this->codigo) {
            $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $this->nombre), 0, 3)) . $this->id;


            $codigo = $baseCode;
            $counter = 1;

            while (self::where('codigo', $codigo)->exists()) {
                $codigo = $baseCode . $counter;
                $counter++;
            }

            $this->update(['codigo' => $codigo]);
        }

        return $this->codigo;
    }

    public function getActiveWhatsAppSessions()
    {
        return $this->whatsappSessions()
            ->where('status', 'connected')
            ->where('last_message_at', '>=', now()->subHours(24))
            ->get();
    }

    public function getWhatsAppStatsToday()
    {
        return [
            'messages_received' => $this->whatsappMessages()
                ->incoming()
                ->today()
                ->count(),
            'messages_sent' => $this->whatsappMessages()
                ->outgoing()
                ->today()
                ->count(),
            'active_sessions' => $this->whatsappSessions()
                ->where('last_message_at', '>=', now()->subHours(24))
                ->count()
        ];
    }

    /**
     * Verificar si los tickets deben generarse en PDF
     */
    public function ticketEnPdf()
    {
        return $this->getCustomConfigValue('configuraciones', 'ticket_en_pdf', false);
    }

    /**
     * Establecer si los tickets deben generarse en PDF
     */
    public function setTicketEnPdf($pdf)
    {
        return $this->updateCustomConfig('configuraciones', 'ticket_en_pdf', $pdf);
    }

    /**
     * Alternar entre ticket HTML y PDF
     */
    public function toggleTicketEnPdf()
    {
        $actualValue = $this->ticketEnPdf();
        return $this->setTicketEnPdf(!$actualValue);
    }

    public function empresaFuncionalidad()
    {
        return $this->hasMany('App\Models\Admin\EmpresaFuncionalidad', 'id_empresa');
    }

    public function getAccesChatbotWhatsappAttribute()
    {
        $funcionalidad = $this->empresaFuncionalidad()->where('id_funcionalidad', 2)->first();
        return $funcionalidad ? $funcionalidad->activo : false;
    }


    public function getShopifyWebhookUrlAttribute()
    {
        if (empty($this->woocommerce_api_key)) {
            return null;
        }
        return url('/api/webhook/shopify/' . $this->woocommerce_api_key);
    }

    public function getStatusConexionShopifyAttribute()
    {
        $connected_users = $this->usuarios->where('shopify_status', 'connected');

        if ($connected_users->count() > 0) {
            return 'connected';
        }

        return 'disconnected';
    }

    public function getIsCurrentUserConnectedToShopifyAttribute()
    {
        $current_user = Auth::user();
        return $current_user && $current_user->shopify_status === 'connected';
    }
}
