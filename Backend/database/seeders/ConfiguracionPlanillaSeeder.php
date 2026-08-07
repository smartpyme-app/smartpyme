<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\Empresa;
use App\Services\Planilla\PlanillaTemplatesService;

class ConfiguracionPlanillaSeeder extends Seeder
{
    private const CODIGOS_VALIDOS = ['SV', 'GT', 'HN', 'NI', 'CR', 'PA', 'BZ'];

    public function run(): void
    {
        $empresas = Empresa::all();

        foreach ($empresas as $empresa) {
            $codPais = $this->resolverCodigoPais($empresa);

            $configActual = DB::table('empresa_configuracion_planilla')
                ->where('empresa_id', $empresa->id)
                ->where('activo', true)
                ->first();

            if (!$configActual) {
                $this->crearConfiguracion($empresa, $codPais);
                continue;
            }

            if ($configActual->cod_pais !== $codPais) {
                echo "♻️ Empresa {$empresa->id} ({$empresa->nombre}): config {$configActual->cod_pais} → {$codPais}\n";
                $this->actualizarConfiguracion($configActual->id, $codPais);
                continue;
            }

            echo "⚠️ Configuración {$codPais} ya existe para: {$empresa->nombre}\n";
        }

        echo "🎉 Configuraciones creadas/actualizadas usando PlanillaTemplatesService\n";
    }

    /**
     * Preferir cod_pais válido; si falta, mapear desde nombre.
     * Si ambos existen y discrepan, avisar y usar cod_pais.
     */
    private function resolverCodigoPais(Empresa $empresa): string
    {
        $desdeCodigo = $empresa->cod_pais
            ? strtoupper(trim($empresa->cod_pais))
            : null;
        $desdeNombre = $this->mapearCodigoPais($empresa->pais);

        $codigoValido = $desdeCodigo && in_array($desdeCodigo, self::CODIGOS_VALIDOS, true)
            ? $desdeCodigo
            : null;

        if ($codigoValido && $desdeNombre && $codigoValido !== $desdeNombre) {
            echo "⚠️ Discrepancia empresa {$empresa->id}: cod_pais={$codigoValido} vs pais='{$empresa->pais}'→{$desdeNombre}. Usando cod_pais.\n";
            return $codigoValido;
        }

        if ($codigoValido) {
            return $codigoValido;
        }

        if ($desdeNombre) {
            if (!$empresa->cod_pais) {
                echo "ℹ️ Empresa {$empresa->id}: cod_pais vacío, usando pais='{$empresa->pais}' → {$desdeNombre}\n";
            }
            return $desdeNombre;
        }

        echo "ℹ️ Empresa {$empresa->id}: sin país reconocible, default SV\n";
        return 'SV';
    }

    private function mapearCodigoPais(?string $nombrePais): ?string
    {
        if ($nombrePais === null || trim($nombrePais) === '') {
            return null;
        }

        $mapeo = [
            'El Salvador' => 'SV',
            'Guatemala' => 'GT',
            'Honduras' => 'HN',
            'Nicaragua' => 'NI',
            'Costa Rica' => 'CR',
            'Panama' => 'PA',
            'Panamá' => 'PA',
            'Belice' => 'BZ',
            'Belize' => 'BZ',
        ];

        return $mapeo[trim($nombrePais)] ?? null;
    }

    private function crearConfiguracion(Empresa $empresa, string $codPais): void
    {
        DB::table('empresa_configuracion_planilla')->insert([
            'empresa_id' => $empresa->id,
            'cod_pais' => $codPais,
            'configuracion' => json_encode(PlanillaTemplatesService::getConfiguracionPorPais($codPais)),
            'activo' => true,
            'fecha_vigencia_desde' => now(),
            'fecha_vigencia_hasta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "✅ Configuración {$codPais} creada para: {$empresa->nombre}\n";
    }

    private function actualizarConfiguracion(int $configId, string $codPais): void
    {
        // ponytail: regenera plantilla al corregir país; si había conceptos custom, se pierden — upgrade: merge o versionar
        DB::table('empresa_configuracion_planilla')
            ->where('id', $configId)
            ->update([
                'cod_pais' => $codPais,
                'configuracion' => json_encode(PlanillaTemplatesService::getConfiguracionPorPais($codPais)),
                'updated_at' => now(),
            ]);

        echo "✅ Configuración actualizada a {$codPais} (id={$configId})\n";
    }
}
