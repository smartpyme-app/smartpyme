<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use JWTAuth;

class Proyecto extends Model {
    use SoftDeletes;
    protected $table = 'proyectos';
    protected $fillable = array(
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'descripcion',
        'estado',
        'enable',
        'id_cliente',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );

    protected $casts = ['enable' => 'string'];
    protected $appends = ['nombre_cliente', 'ingresos_esperados', 'gastos_esperados', 'compras_esperados', 'ingresos_generados', 'gastos_generados', 'compras_generados'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function getNombreClienteAttribute()
    {   $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return '';
    }

    public function getIngresosEsperadosAttribute(){
        return $this->cotizaciones()->sum('total');
    }

    public function getGastosEsperadosAttribute(){
        return $this->presupuestos()->sum('egresos');
    }

    public function getComprasEsperadosAttribute(){
        return $this->presupuestos()->sum('compras');
    }

    public function getGastosGeneradosAttribute(){
        return $this->gastos()->sum('total');
    }

    public function getComprasGeneradosAttribute(){
        return $this->compras()->sum('total');
    }

    public function getIngresosGeneradosAttribute(){
        return $this->ventas()->sum('total');
    }

    public function cotizaciones(){
        return $this->hasMany('App\Models\Ventas\Venta', 'id_proyecto')->where('cotizacion', 1);
    }

    public function presupuesto(){
        return $this->hasOne('App\Models\Contabilidad\Presupuesto', 'id_proyecto');
    }

    public function presupuestos(){
        return $this->hasMany('App\Models\Contabilidad\Presupuesto', 'id_proyecto');
    }

    public function gastos(){
        return $this->hasMany('App\Models\Compras\Gastos\Gasto', 'id_proyecto');
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Compra', 'id_proyecto');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Venta', 'id_proyecto')->where('cotizacion', 0);
    }

    public function cliente(){
        return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'id_cliente');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
