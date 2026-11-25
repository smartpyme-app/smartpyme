<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Abono extends Model {

    protected $table = 'abonos_ventas';
    protected $fillable = array(
        'fecha',
        'correlativo',
        'id_documento',
        'concepto',
        'referencia',
        'estado',
        'tipo_dte',
        'codigo_generacion',
        'sello_mh',
        'dte',
        'nombre_de',
        'forma_pago',
        'detalle_banco',
        'mora',
        'comision',
        'total',
        'nota',
        'id_caja',
        'id_corte',
        'id_venta',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','id_venta');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function documento(){
        return $this->belongsTo('App\Models\Admin\Documento','id_documento');
    }

    protected $appends = ['nombre_documento'];

    public function getNombreDocumentoAttribute(){
        return $this->documento()->pluck('nombre')->first();
    }

    /**
     * FE CR guarda XML firmado (string), igual que ventas. Si quedó JSON-encodeado por un cast previo, se desenvuelve.
     */
    public function getDteAttribute(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }
        $trim = ltrim($value);
        if (str_starts_with($trim, '<?xml') || str_starts_with($trim, '<')) {
            return $value;
        }
        if (str_starts_with($trim, '"') || str_starts_with($trim, '{') || str_starts_with($trim, '[')) {
            $decoded = json_decode($value, true);
            if (is_string($decoded) || is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

}
