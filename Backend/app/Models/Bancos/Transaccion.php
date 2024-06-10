<?php

namespace App\Models\Bancos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    use HasFactory;
    protected $table = 'cuentas_bancarias_transacciones';
    protected $fillable = [
        'fecha',
        'id_cuenta',
        'concepto',
        'tipo',
        'total',
        'id_empresa',
        'id_usuario',
    ];


    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function cuenta(){
        return $this->belongsTo('App\Models\Bancos\Cuenta', 'id_cuenta');
    }
    
}
