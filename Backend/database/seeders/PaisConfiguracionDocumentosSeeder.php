<?php

namespace Database\Seeders;

use App\Models\PaisConfiguracion;
use App\Support\Admin\DocumentosDefaultPorPais;
use Illuminate\Database\Seeder;

class PaisConfiguracionDocumentosSeeder extends Seeder
{
    public function run()
    {
        foreach (['SV', 'CR', 'HN'] as $pais) {
            PaisConfiguracion::updateOrCreate(
                [
                    'pais' => $pais,
                    'modulo' => PaisConfiguracion::MODULO_DOCUMENTOS,
                ],
                [
                    'configuracion' => DocumentosDefaultPorPais::plantilla($pais),
                ]
            );
        }
    }
}
