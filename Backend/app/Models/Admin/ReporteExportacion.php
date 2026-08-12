<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ReporteExportacion extends Model
{
    protected $table = 'reporte_exportaciones';

    public const MODO_DOWNLOAD = 'download';
    public const MODO_EMAIL = 'email';

    public const FORMATO_EXCEL = 'excel';
    public const FORMATO_PDF = 'pdf';

    public const ESTADO_PENDING = 'pending';
    public const ESTADO_PROCESSING = 'processing';
    public const ESTADO_DONE = 'done';
    public const ESTADO_FAILED = 'failed';

    protected $fillable = [
        'id_empresa',
        'id_usuario',
        'id_configuracion',
        'modo',
        'formato',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'sucursales',
        'destinatarios',
        'ruta_archivo',
        'nombre_archivo',
        'error',
    ];

    protected $casts = [
        'sucursales' => 'array',
        'destinatarios' => 'array',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    public function configuracion()
    {
        return $this->belongsTo(ReporteConfiguracion::class, 'id_configuracion');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function absolutePath(): ?string
    {
        if (!$this->ruta_archivo) {
            return null;
        }

        return storage_path('app/' . ltrim($this->ruta_archivo, '/'));
    }
}
