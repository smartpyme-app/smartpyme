<?php

namespace App\Models\Bancos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cheque extends Model
{
    use HasFactory;
    protected $table = 'cheques';
    protected $fillable = [
        'fecha',
        'id_cuenta',
        'correlativo',
        'anombrede',
        'concepto',
        'total',
        'id_usuario',
        'id_empresa',
    ];


    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function cuenta(){
        return $this->belongsTo('App\Models\Bancos\Cuenta', 'id_cuenta');
    }

}
