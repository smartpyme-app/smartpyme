<?php

namespace App\Models\Contabilidad\Catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Saldo extends Model
{
    use HasFactory;
    protected $table = 'catalogo_cuenta_saldos';
    protected $fillable = [
        'anio',
        'mes',
        'saldo_inicial',
        'abonos',
        'cargos',
        'saldo_final',
        'id_cuenta',
    ];


    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
    
}
