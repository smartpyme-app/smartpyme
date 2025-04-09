<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\Empresa;

class Currency extends Model
{
    use HasFactory;
    protected $table = 'currencies';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'currency_code',
        'currency_name',
        'currency_symbol',
        'country_code',
    ];


    public function empresas()
    {
        return $this->hasMany(Empresa::class, 'moneda', 'currency_code');
    }

}
