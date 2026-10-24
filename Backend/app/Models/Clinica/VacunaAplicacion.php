<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class VacunaAplicacion extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_vacuna_aplicaciones';

    protected $fillable = [
        'id_empresa',
        'id_expediente',
        'id_paciente',
        'id_catalogo',
        'nombre_vacuna',
        'tipo',
        'fecha_aplicacion',
        'dosis',
        'id_sucursal',
        'id_usuario_profesional',
        'id_usuario_registro',
        'id_producto',
        'id_lote',
        'lote_numero',
        'lote_vencimiento',
        'fabricante',
        'proxima_fecha',
        'observaciones',
        'estado',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_aplicacion' => 'date',
        'lote_vencimiento' => 'date',
        'proxima_fecha' => 'date',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }
}
