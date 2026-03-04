<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\Empresa;
use App\Constants\PlanillaConstants;
use App\Services\Planilla\PlanillaTemplatesService;

class ConfiguracionPlanillaSeeder extends Seeder
{

    public function run(): void
    {
        $empresas = Empresa::all();

        foreach ($empresas as $empresa) {
            $existeConfiguracion = DB::table('empresa_configuracion_planilla')
                ->where('empresa_id', $empresa->id)
                ->where('activo', true)
                ->exists();

            if (!$existeConfiguracion) {
                
                echo "🔍 Empresa: {$empresa->nombre} - País: '{$empresa->pais}'\n";
    
                $codPais = $this->mapearCodigoPais($empresa->pais ?? 'El Salvador');
                
                echo "🔍 Código asignado: {$codPais}\n";
                
                $configuracion = PlanillaTemplatesService::getConfiguracionPorPais($codPais);

                DB::table('empresa_configuracion_planilla')->insert([
                    'empresa_id' => $empresa->id,
                    'cod_pais' => $codPais,
                    'configuracion' => json_encode($configuracion),
                    'activo' => true,
                    'fecha_vigencia_desde' => now(),
                    'fecha_vigencia_hasta' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                echo "✅ Configuración {$codPais} creada para: {$empresa->nombre}\n";
            } else {
                echo "⚠️ Configuración ya existe para: {$empresa->nombre}\n";
            }
        }

        echo "🎉 Configuraciones creadas usando PlanillaTemplatesService\n";
    }

      /**
     * Mapear nombres de país a códigos
     */
    private function mapearCodigoPais($nombrePais)
    {
        $mapeo = [
            'El Salvador' => 'SV',
            'Guatemala' => 'GT',
            'Honduras' => 'HN',
            'Nicaragua' => 'NI',
            'Costa Rica' => 'CR',
            'Panama' => 'PA',
            'Panamá' => 'PA',
            'Belice' => 'BZ'
        ];

        return $mapeo[$nombrePais] ?? 'SV';
    }


}