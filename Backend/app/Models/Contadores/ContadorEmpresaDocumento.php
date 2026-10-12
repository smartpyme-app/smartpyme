<?php

namespace App\Models\Contadores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContadorEmpresaDocumento extends Model
{
    protected $table = 'contador_empresa_documentos';

    protected $fillable = [
        'id_empresa',
        'slug',
        'titulo',
        'ruta',
        'nombre_archivo',
        'mime',
        'tamano_bytes',
        'vence_en',
        'id_usuario_carga',
    ];

    protected $casts = [
        'vence_en' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
}
