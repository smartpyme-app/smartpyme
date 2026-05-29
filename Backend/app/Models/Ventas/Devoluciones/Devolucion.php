<?php

namespace App\Models\Ventas\Devoluciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Devolucion extends Model {

    protected $table = 'devoluciones_venta';
    protected $fillable = array(
        'tipo_dte',
        'tipo',
        'numero_control',
        'codigo_generacion',
        'sello_mh',
        'dte',
        'dte_invalidacion',
        'fecha',
        'correlativo',
        'id_documento',
        'sub_total',
        'no_sujeta',
        'exenta',
        'cuenta_a_terceros',
        'total',
        'iva',
        'iva_retenido',
        'observaciones',
        'id_cliente',
        'id_bodega',
        'id_sucursal',
        'id_empresa',
        'enable',
        'id_venta',
        'id_usuario'
    );

    protected $appends = ['nombre_cliente', 'nombre_usuario', 'nombre_documento'];
    protected $casts = ['enable' => 'boolean'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function getDteAttribute($value)
    {
        return $this->decodeStoredDte($this->attributes['dte'] ?? null);
    }

    public function setDteAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['dte'] = null;

            return;
        }
        $this->attributes['dte'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;
    }

    public function getDteInvalidacionAttribute($value)
    {
        return $this->decodeStoredDte($this->attributes['dte_invalidacion'] ?? null);
    }

    public function setDteInvalidacionAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['dte_invalidacion'] = null;

            return;
        }
        $this->attributes['dte_invalidacion'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;
    }

    /**
     * JSON (SV / legado CR) o XML string (FE Costa Rica).
     *
     * @return array|string|null
     */
    private function decodeStoredDte($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_string($raw)) {
            return $raw;
        }
        $trim = ltrim($raw);
        if ($trim === '') {
            return null;
        }
        if ($trim[0] === '{' || $trim[0] === '[') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : $raw;
        }

        return $raw;
    }

    public function getNombreDocumentoAttribute(){
        return $this->documento()->pluck('nombre')->first();
    }

    public function getNombreClienteAttribute()
    {   $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return 'Consumidor Final';
    }

    public function getNombreAttribute($name)
    {
        return strtoupper($name);
    }


    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }


    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente','id_cliente');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function documento(){
        return $this->belongsTo('App\Models\Admin\Documento','id_documento');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','id_venta');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Ventas\Devoluciones\Detalle','id_devolucion_venta');
    }


}
