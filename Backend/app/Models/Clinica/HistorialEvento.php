<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class HistorialEvento extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_historial_eventos';

    protected $fillable = [
        'id_empresa',
        'id_expediente',
        'tipo',
        'fecha_evento',
        'hora_evento',
        'id_usuario_profesional',
        'origen_tipo',
        'origen_id',
        'resumen',
        'estado',
    ];

    protected $casts = [
        'fecha_evento' => 'date',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'id_expediente');
    }
}
