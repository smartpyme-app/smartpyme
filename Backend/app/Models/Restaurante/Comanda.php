<?php

namespace App\Models\Restaurante;

use App\Models\Concerns\AuditableModel;
use Illuminate\Database\Eloquent\Model;

class Comanda extends AuditableModel
{
    protected static function auditModule(): string
    {
        return 'restaurante';
    }

    public function transformAudit(array $data): array
    {
        $data['id_empresa'] = $this->pedido_id
            ? PedidoRestaurante::withoutGlobalScopes()->where('id', $this->pedido_id)->value('id_empresa')
            : null;
        $data['id_empresa'] ??= auth()->user()?->id_empresa;
        $data['module'] = 'restaurante';

        return $data;
    }

    protected $table = 'comandas_restaurante';

    protected $fillable = [
        'sesion_id',
        'pedido_id',
        'numero_comanda',
        'estado',
        'destino',
        'eliminacion_item_enviado',
        'motivo_eliminacion_codigo',
        'motivo_eliminacion_detalle',
        'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'eliminacion_item_enviado' => 'boolean',
    ];

    public function sesion()
    {
        return $this->belongsTo(SesionMesa::class, 'sesion_id');
    }

    public function pedido()
    {
        return $this->belongsTo(PedidoRestaurante::class, 'pedido_id');
    }

    public function detalles()
    {
        return $this->hasMany(ComandaDetalle::class, 'comanda_id');
    }
}
