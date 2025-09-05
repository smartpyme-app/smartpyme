<?php

namespace Database\Seeders\FidelizacionCliente;

use Illuminate\Database\Seeder;
use App\Models\Admin\Empresa;
use App\Models\FidelizacionClientes\TipoClienteBase;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use Carbon\Carbon;

class ConfiguracionFidelizacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Obtener todas las empresas existentes
        $empresas = Empresa::all();

        // Obtener todos los tipos base
        $tiposBase = TipoClienteBase::all();

        if ($tiposBase->isEmpty()) {
            $this->command->error('No existen tipos de cliente base. Ejecuta primero TiposClienteBaseSeeder.');
            return;
        }

        $this->command->info("Configurando fidelización para {$empresas->count()} empresa(s)...");

        // Crear configuraciones para cada empresa
        foreach ($empresas as $empresa) {
            $this->command->info("Configurando empresa: {$empresa->nombre}");

            foreach ($tiposBase as $tipoBase) {
                // Verificar si ya existe la configuración
                $existeConfiguracion = TipoClienteEmpresa::where('id_empresa', $empresa->id)
                    ->where('nivel', $tipoBase->orden)
                    ->exists();

                if ($existeConfiguracion) {
                    $this->command->warn("  - {$tipoBase->nombre} ya existe, omitiendo...");
                    continue;
                }

                // Crear configuración
                TipoClienteEmpresa::create([
                    'id_empresa' => $empresa->id,
                    'id_tipo_base' => $tipoBase->id,
                    'nivel' => $tipoBase->orden,
                    'nombre_personalizado' => null, // Usará el nombre del tipo base
                    'activo' => true,
                    'puntos_por_dolar' => $this->getPuntosPorDolar($tipoBase->code),
                    'minimo_canje' => $this->getMinimoCanje($tipoBase->code),
                    'maximo_canje' => $this->getMaximoCanje($tipoBase->code),
                    'expiracion_meses' => $this->getExpiracionMeses($tipoBase->code),
                    'configuracion_avanzada' => $this->getConfiguracionAvanzada($tipoBase->code),
                    'is_default' => $tipoBase->code === 'STANDARD',
                ]);

                $this->command->info("  ✓ {$tipoBase->nombre} configurado");
            }

            $this->command->info("Empresa {$empresa->nombre} configurada completamente.\n");
        }

        $this->command->info('🎉 Configuración de fidelización completada para todas las empresas.');
    }

    /**
     * Obtener puntos por dólar según el tipo
     */
    private function getPuntosPorDolar(string $code): float
    {
        switch($code) {
            case 'STANDARD':
                return 1.0;
            case 'VIP':
                return 1.5;
            case 'ULTRAVIP':
                return 2.0;
            default:
                return 1.0;
        }
    }

    /**
     * Obtener mínimo de canje según el tipo
     */
    private function getMinimoCanje(string $code): int
    {
        switch($code) {
            case 'STANDARD':
                return 100;
            case 'VIP':
                return 50;
            case 'ULTRAVIP':
                return 25;
            default:
                return 100;
        }
    }

    /**
     * Obtener máximo de canje según el tipo
     */
    private function getMaximoCanje(string $code): int
    {
        switch($code) {
            case 'STANDARD':
                return 1000;
            case 'VIP':
                return 2000;
            case 'ULTRAVIP':
                return 5000;
            default:
                return 1000;
        }
    }

    /**
     * Obtener meses de expiración según el tipo
     */
    private function getExpiracionMeses(string $code): int
    {
        switch($code) {
            case 'STANDARD':
                return 12;
            case 'VIP':
                return 18;
            case 'ULTRAVIP':
                return 24;
            default:
                return 12;
        }
    }

    /**
     * Obtener configuración avanzada según el tipo
     */
    private function getConfiguracionAvanzada(string $code): array
    {
        $baseConfig = [
            'valor_punto' => 0.01, // $0.01 por punto al canjear
            'multiplicador_especial' => false,
            'descuento_cumpleanos' => false,
        ];

        switch($code) {
            case 'STANDARD':
                return $baseConfig;
            case 'VIP':
                return array_merge($baseConfig, [
                    'multiplicador_especial' => true,
                    'multiplicador_valor' => 1.2,
                    'descuento_cumpleanos' => true,
                    'descuento_cumpleanos_porcentaje' => 5,
                ]);
            case 'ULTRAVIP':
                return array_merge($baseConfig, [
                    'multiplicador_especial' => true,
                    'multiplicador_valor' => 1.5,
                    'descuento_cumpleanos' => true,
                    'descuento_cumpleanos_porcentaje' => 10,
                    'acceso_exclusivo' => true,
                    'soporte_prioritario' => true,
                ]);
            default:
                return $baseConfig;
        }
    }
}