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
                    'valor_punto' => $this->getValorPunto($tipoBase->code),
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
                return 1.25;
            case 'ULTRAVIP':
                return 1.5;
            default:
                return 1.0;
        }
    }

    /**
     * Obtener valor del punto según el tipo
     *
     * STANDARD: 0.010
     * VIP: 0.0105 (puedes ajustar a 0.012 si quieres el extremo superior)
     * ULTRAVIP: 0.012 (puedes ajustar a 0.015 si quieres el extremo superior)
     */
    private function getValorPunto(string $code): float
    {
        switch($code) {
            case 'STANDARD':
                return 0.010;
            case 'VIP':
                return 0.011; // valor intermedio recomendado
            case 'ULTRAVIP':
                return 0.0135; // valor intermedio recomendado
            default:
                return 0.010;
        }
    }

    /**
     * Obtener mínimo de canje según el tipo
     */
    private function getMinimoCanje(string $code): int
    {
        switch($code) {
            case 'STANDARD':
                return 300;
            case 'VIP':
                return 200;
            case 'ULTRAVIP':
                return 150;
            default:
                return 300;
        }
    }

    /**
     * Obtener máximo de canje según el tipo
     */
    private function getMaximoCanje(string $code): int
    {
        switch($code) {
            case 'STANDARD':
                return 1500;
            case 'VIP':
                return 1800;
            case 'ULTRAVIP':
                return 2200;
            default:
                return 1500;
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
            'multiplicador_especial' => false,
            'descuento_cumpleanos' => false,
            'tope_canje_porcentaje_ticket' => 0.15, // default 15%
            'upgrade_automatico' => [
                'habilitado' => true,
                'reglas' => []
            ]
        ];

        switch($code) {
            case 'STANDARD':
                return array_merge($baseConfig, [
                    'tope_canje_porcentaje_ticket' => 0.15, // 15%
                    'upgrade_automatico' => [
                        'habilitado' => true,
                        'reglas' => [
                            [
                                'tipo' => 'gasto_total',
                                'umbral' => 500.00,
                                'nivel_destino' => 2,
                                'descripcion' => 'Upgrade a VIP por $500+ gastados',
                                'activo' => true
                            ],
                            [
                                'tipo' => 'puntos_acumulados',
                                'umbral' => 800,
                                'nivel_destino' => 2,
                                'descripcion' => 'Upgrade a VIP por 800+ puntos acumulados',
                                'activo' => true
                            ]
                        ]
                    ]
                ]);

            case 'VIP':
                return array_merge($baseConfig, [
                    'multiplicador_especial' => true,
                    'multiplicador_valor' => 1.1,
                    'descuento_cumpleanos' => true,
                    'descuento_cumpleanos_porcentaje' => 5,
                    'tope_canje_porcentaje_ticket' => 0.20, // 20%
                    'upgrade_automatico' => [
                        'habilitado' => true,
                        'reglas' => [
                            [
                                'tipo' => 'gasto_total',
                                'umbral' => 2000.00,
                                'nivel_destino' => 3,
                                'descripcion' => 'Upgrade a Ultra VIP por $2000+ gastados',
                                'activo' => true
                            ],
                            [
                                'tipo' => 'puntos_acumulados',
                                'umbral' => 3000,
                                'nivel_destino' => 3,
                                'descripcion' => 'Upgrade a Ultra VIP por 3000+ puntos',
                                'activo' => true
                            ],
                            [
                                'tipo' => 'compras_periodo',
                                'umbral' => 15,
                                'periodo_meses' => 6,
                                'nivel_destino' => 3,
                                'descripcion' => 'Upgrade a Ultra VIP por 15+ compras en 6 meses',
                                'activo' => true
                            ]
                        ]
                    ]
                ]);

            case 'ULTRAVIP':
                return array_merge($baseConfig, [
                    'multiplicador_especial' => true,
                    'multiplicador_valor' => 1.2,
                    'descuento_cumpleanos' => true,
                    'descuento_cumpleanos_porcentaje' => 10,
                    'tope_canje_porcentaje_ticket' => 0.30, // 30%
                    'acceso_exclusivo' => true,
                    'soporte_prioritario' => true,
                    // 'beneficios_exclusivos' => [
                    //     'descuento_maximo_adicional' => 15, // 15% descuento extra
                    //     'puntos_bienvenida_anual' => 500,  // 500 puntos gratis cada año
                    //     'acceso_eventos_vip' => true,
                    //     'entrega_express_gratis' => true,
                    //     'asistente_personal' => true
                    // ],
                    'upgrade_automatico' => [
                        'habilitado' => false, // Ultra VIP es el máximo nivel
                        'reglas' => []
                    ]
                ]);

            default:
                return $baseConfig;
        }
    }
}