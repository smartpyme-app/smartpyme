<?php

namespace App\Models\Ventas;

use App\Models\FidelizacionClientes\TransaccionPuntos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Venta extends Model {

    protected $table = 'ventas';
    protected $fillable = array(
        'tipo_dte',
        'numero_control',
        'codigo_generacion',
        'sello_mh',
        'prueba_masiva',
        'fecha',
        'correlativo',
        'num_identificacion',
        'estado',
        'detalle_banco',
        // 'tipo',
        'id_canal',
        'id_documento',
        'forma_pago',
        'tipo_documento',
        'num_cotizacion',
        'num_orden',
        'num_orden_exento',
        'condicion',
        'referencia',
        // 'nombre',
        'fecha_pago',
        'fecha_expiracion',
        'monto_pago',
        'cambio',
        'iva_percibido',
        'iva_retenido',
        'renta_retenida',
        'iva',
        'total_costo',
        'descuento',
        'sub_total',
        'no_sujeta',
        'exenta',
        'gravada',
        'cuenta_a_terceros',
        'total',
        'propina',
        'observaciones',
        'recurrente',
        'cotizacion',
        'descripcion_personalizada',
        'descripcion_impresion',
        'id_caja',
        'id_proyecto',
        'id_bodega',
        'id_corte',
        'id_cliente',
        'id_usuario',
        'id_vendedor',
        'id_empresa',
        'id_sucursal',
        'dte',
        'dte_invalidacion',
        'tipo_item_export',
        'importado',
        'cod_incoterm',
        'incoterm',
        'recinto_fiscal',
        'regimen',
        'seguro',
        'flete',
        'no_sujeta',
        'tipo_operacion',
        'tipo_renta',
        'puntos_ganados',
        'puntos_canjeados',
        'descuento_puntos',
        'referencia_shopify',
        'fecha_anulacion',
        'tipo_anulacion',
        'motivo_anulacion',
        'codigo_generacion_remplazo',
    );

    protected $appends = ['nombre_cliente', 'nombre_usuario', 'nombre_vendedor',  'nombre_sucursal', 'nombre_canal', 'nombre_documento', 'nombre_proyecto'];
    protected $casts = [
        'recurrente' => 'string',
        'puntos_ganados' => 'integer',
        'puntos_canjeados' => 'integer',
        'descuento_puntos' => 'decimal:2'
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

    public function getNombreClienteAttribute()
    {   $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return 'Consumidor Final';
    }

    public function getDteAttribute($value)
    {
        return is_string($value) ? json_decode($value,true) : $value;
    }

    public function getDteInvalidacionAttribute($value)
    {
        return is_string($value) ? json_decode($value,true) : $value;
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreVendedorAttribute()
    {
        return $this->vendedor()->pluck('name')->first();
    }

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function getNombreDocumentoAttribute(){
        return $this->documento()->pluck('nombre')->first();
    }

    public function getNombreCanalAttribute(){
        return $this->canal()->pluck('nombre')->first();
    }


    public function getNombreProyectoAttribute()
    {
        return $this->proyecto ? $this->proyecto->nombre : null;
    }

    public function detalleText(){
        $text = '';

        foreach ($this->detalles as $detalle) {
            $text .= $detalle->nombre_producto . ' X ' . $detalle->cantidad . '. ';
            if ($detalle->producto()->first()->promocion()->first()){
              foreach ($detalle->producto()->first()->promocion()->first()->detalles()->get() as $det){
                $text .= ' - ' . $det->nombre_producto . ' X ' . $det->cantidad . '. ';
              }
            }
        }

        return $text;
    }

    public function getSaldoAttribute(){
        $abonos = $this->abonos()->where('estado', 'Confirmado')->sum('total');
        $devoluciones = $this->devoluciones()->where('enable', 1)->sum('total');
        return round($this->total - $abonos - $devoluciones,2);
    }

    // Relaciones

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','id_cliente');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function vendedor(){
        return $this->belongsTo('App\Models\User','id_vendedor');
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_bodega');
    }

    public function canal(){
        return $this->belongsTo('App\Models\Admin\Canal','id_canal');
    }

    public function impuestos(){
        return $this->hasMany('App\Models\Ventas\Impuesto', 'id_venta');
    }

    public function metodos_de_pago(){
        return $this->hasMany('App\Models\Ventas\MetodoDePago', 'id_venta');
    }

    public function documento(){
        return $this->belongsTo('App\Models\Admin\Documento','id_documento');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Ventas\Detalle','id_venta');
    }

    public function abonos(){
        return $this->hasMany('App\Models\Ventas\Abono','id_venta');
    }

    public function cotizacion(){
        return $this->hasOne('App\Models\Ventas\Venta','num_cotizacion');
    }

    public function devoluciones(){
        return $this->hasMany('App\Models\Ventas\Devoluciones\Devolucion', 'id_venta');
    }

    public function proyecto()
    {
        return $this->belongsTo('App\Models\Contabilidad\Proyecto', 'id_proyecto');
    }

    public function transaccionPuntos()
    {
        return $this->hasOne(TransaccionPuntos::class, 'id_venta');
    }

    public function transaccionesPuntos()
    {
        return $this->hasMany(TransaccionPuntos::class, 'id_venta');
    }

    public function getMontoFinalAttribute()
    {
        return $this->total - ($this->descuento_puntos ?? 0);
    }

    public function getTienePuntosAttribute()
    {
        return $this->puntos_ganados > 0 || $this->puntos_canjeados > 0;
    }

    public function getPorcentajeDescuentoAttribute()
    {
        if ($this->total == 0) return 0;
        return round((($this->descuento_puntos ?? 0) / $this->total) * 100, 2);
    }

    public function calcularPuntosEsperados()
    {
        $tipoCliente = $this->cliente->getTipoClienteEfectivo();
        if (!$tipoCliente) return 0;

        return $tipoCliente->calcularPuntos($this->getMontoFinal());
    }

    public function tienePuntosGenerados()
    {
        return $this->transaccionPuntos &&
               $this->transaccionPuntos->tipo === TransaccionPuntos::TIPO_GANANCIA;
    }

    public function generarPuntos()
    {
        if ($this->tienePuntosGenerados()) {
            return false; // Ya tiene puntos generados
        }

        $puntosCalculados = $this->calcularPuntosEsperados();

        if ($puntosCalculados > 0) {
            $this->update(['puntos_ganados' => $puntosCalculados]);
            return $puntosCalculados;
        }

        return 0;
    }

    public function aplicarDescuentoPuntos($puntos)
    {
        $tipoCliente = $this->cliente->getTipoClienteEfectivo();
        if (!$tipoCliente) return false;

        $descuento = $tipoCliente->calcularDescuento($puntos);
        $maxDescuento = min($descuento, $this->total);

        $this->update([
            'puntos_canjeados' => $puntos,
            'descuento_puntos' => $maxDescuento,
        ]);

        return $maxDescuento;
    }

    public function getResumenPuntos()
    {
        return [
            'puntos_ganados' => $this->puntos_ganados ?? 0,
            'puntos_canjeados' => $this->puntos_canjeados ?? 0,
            'descuento_aplicado' => $this->descuento_puntos ?? 0,
            'porcentaje_descuento' => $this->porcentaje_descuento,
            'monto_original' => $this->total,
            'monto_final' => $this->monto_final,
        ];
    }

    public function isVentaConPuntos()
    {
        return $this->tiene_puntos;
    }

    public function getValorPromedioPorPunto()
    {
        if (!$this->puntos_canjeados || !$this->descuento_puntos) {
            return 0;
        }
        return $this->descuento_puntos / $this->puntos_canjeados;
    }





}
