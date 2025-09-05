<?php

namespace App\Models\FidelizacionClientes;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumoPuntos extends Model
{
    use HasFactory;

    protected $table = 'consumo_puntos';

    protected $fillable = [
        'id_empresa',
        'id_cliente',
        'id_canje_tx',
        'id_ganancia_tx',
        'puntos_consumidos',
        'descripcion',
    ];

    protected $casts = [
        'puntos_consumidos' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function transaccionCanje()
    {
        return $this->belongsTo(TransaccionPuntos::class, 'id_canje_tx');
    }

    public function transaccionGanancia()
    {
        return $this->belongsTo(TransaccionPuntos::class, 'id_ganancia_tx');
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('id_cliente', $clienteId);
    }

    public function scopePorCanje($query, $canjeId)
    {
        return $query->where('id_canje_tx', $canjeId);
    }

    public function getFechaExpiracionOriginal()
    {
        return $this->transaccionGanancia->fecha_expiracion;
    }

    public function getFechaGananciaOriginal()
    {
        return $this->transaccionGanancia->created_at;
    }

    public function getDiasDeVida()
    {
        $fechaGanancia = $this->getFechaGananciaOriginal();
        return $fechaGanancia ? $fechaGanancia->diffInDays($this->created_at) : null;
    }
}
