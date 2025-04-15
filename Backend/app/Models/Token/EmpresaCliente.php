<?php

namespace App\Models\Token;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Client;


class EmpresaCliente extends Model
{
    use HasFactory;

    protected $table = 'empresa_clientes';

    protected $fillable = [
        'id_empresa',
        'id_client',
        'id_user',
        'estado'
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function cliente()
    {
        return $this->belongsTo(Client::class, 'id_client');
    }
}
