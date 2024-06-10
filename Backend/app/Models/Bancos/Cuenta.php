<?php

namespace App\Models\Bancos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    use HasFactory;
    protected $table = 'cuentas_bancarias';
    protected $fillable = [
        'numero_cuenta',
        'nombre_banco',
        'tipo_cuenta',
        'saldo',
        'id_empresa',
    ];


    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
}
