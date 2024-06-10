<?php

namespace App\Models\Contabilidad\Catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    use HasFactory;
    protected $table = 'catalogo_cuentas';
    protected $fillable = [
        'codigo',
        'nombre',
        'id_cuenta_mayor',
        'nivel',
        'tipo',
        'sub_cuenta',
        'rubro',
        'id_empresa',
    ];


    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
    
}
