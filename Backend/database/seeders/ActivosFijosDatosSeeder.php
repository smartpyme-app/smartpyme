<?php

namespace Database\Seeders;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Contabilidad\Activo;
use App\Models\Contabilidad\ActivoCategoria;
use App\Models\Contabilidad\ActivoDepreciacion;
use App\Models\User;
use App\Services\Contabilidad\DepreciacionService;
use Illuminate\Database\Seeder;

class ActivosFijosDatosSeeder extends Seeder
{
    public const MARCA_DEMO = '[demo]';

    /** Categorías tipo plantilla SV (referencia contable común). */
    private const CATEGORIAS = [
        [
            'nombre' => 'Mobiliario y equipo de oficina',
            'porcentaje_anual' => 10,
            'vida_util_anios' => 10,
            'permite_bien_usado' => true,
        ],
        [
            'nombre' => 'Equipo de computación',
            'porcentaje_anual' => 33.33,
            'vida_util_anios' => 3,
            'permite_bien_usado' => false,
        ],
        [
            'nombre' => 'Vehículos',
            'porcentaje_anual' => 25,
            'vida_util_anios' => 4,
            'permite_bien_usado' => true,
            'metadata_schema' => [
                ['key' => 'placa', 'label' => 'Placa', 'type' => 'text'],
                ['key' => 'marca', 'label' => 'Marca', 'type' => 'text'],
                ['key' => 'modelo', 'label' => 'Modelo', 'type' => 'text'],
                ['key' => 'anio', 'label' => 'Año', 'type' => 'number'],
                ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
            ],
        ],
        [
            'nombre' => 'Maquinaria y equipo',
            'porcentaje_anual' => 15,
            'vida_util_anios' => 6.67,
            'permite_bien_usado' => true,
        ],
        [
            'nombre' => 'Edificios e instalaciones',
            'porcentaje_anual' => 5,
            'vida_util_anios' => 20,
            'permite_bien_usado' => false,
        ],
    ];

    public function run(): void
    {
        $empresaIds = $this->empresasObjetivo();
        $limpiar = filter_var(env('ACTIVOS_DEMO_LIMPIAR', false), FILTER_VALIDATE_BOOLEAN);

        if ($limpiar) {
            foreach ($empresaIds as $idEmpresa) {
                $this->limpiarDemo((int) $idEmpresa);
            }
        }

        $creadas = 0;
        foreach ($empresaIds as $idEmpresa) {
            if (! $limpiar && $this->yaTieneDemo((int) $idEmpresa)) {
                continue;
            }

            $this->sembrarEmpresa((int) $idEmpresa);
            $creadas++;
        }

        $this->command?->info("Datos demo de activos fijos en {$creadas} empresa(s).");
    }

    /** Borra activos [demo], sus depreciaciones y categorías huérfanas del seed. */
    public function limpiarDemo(int $idEmpresa): void
    {
        $activoIds = Activo::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('referencia', 'like', self::MARCA_DEMO.'%')
            ->pluck('id');

        if ($activoIds->isNotEmpty()) {
            ActivoDepreciacion::withoutGlobalScopes()
                ->whereIn('id_activo', $activoIds)
                ->delete();

            Activo::withoutGlobalScopes()->whereIn('id', $activoIds)->delete();
        }

        $nombres = array_column(self::CATEGORIAS, 'nombre');

        ActivoCategoria::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('nombre', $nombres)
            ->whereDoesntHave('activos', fn ($q) => $q->withoutGlobalScopes())
            ->each(fn (ActivoCategoria $cat) => $cat->delete());

        $this->command?->info("Demo de activos limpiado en empresa {$idEmpresa}.");
    }

    /** ponytail: por defecto 1 empresa; ACTIVOS_DEMO_EMPRESA_ID o ACTIVOS_DEMO_TODAS_EMPRESAS=true para más */
    private function empresasObjetivo()
    {
        if ($id = env('ACTIVOS_DEMO_EMPRESA_ID')) {
            return collect([(int) $id]);
        }

        $query = User::query()->whereNotNull('id_empresa')->distinct()->orderBy('id_empresa');

        if (filter_var(env('ACTIVOS_DEMO_TODAS_EMPRESAS', false), FILTER_VALIDATE_BOOLEAN)) {
            return $query->pluck('id_empresa');
        }

        $primera = $query->value('id_empresa');
        if ($primera) {
            return collect([(int) $primera]);
        }

        return Empresa::query()->orderBy('id')->limit(1)->pluck('id');
    }

    private function yaTieneDemo(int $idEmpresa): bool
    {
        return Activo::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('referencia', 'like', self::MARCA_DEMO.'%')
            ->exists();
    }

    private function sembrarEmpresa(int $idEmpresa): void
    {
        $usuarioId = User::query()->where('id_empresa', $idEmpresa)->value('id');
        $sucursalId = Sucursal::query()->where('id_empresa', $idEmpresa)->value('id');
        $responsableId = User::query()->where('id_empresa', $idEmpresa)->skip(1)->value('id') ?? $usuarioId;

        $categorias = [];
        foreach (self::CATEGORIAS as $def) {
            $categorias[$def['nombre']] = ActivoCategoria::withoutGlobalScopes()->firstOrCreate(
                ['nombre' => $def['nombre'], 'id_empresa' => $idEmpresa],
                [
                    'metodo_depreciacion' => 'linea_recta',
                    'porcentaje_anual' => $def['porcentaje_anual'],
                    'vida_util_anios' => $def['vida_util_anios'],
                    'valor_residual_default' => 0,
                    'permite_bien_usado' => $def['permite_bien_usado'],
                    'metadata_schema' => $def['metadata_schema'] ?? null,
                ]
            );
        }

        $activos = [
            [
                'nombre' => 'Laptop Dell Latitude 5540',
                'referencia' => self::MARCA_DEMO.' AF-001',
                'categoria' => 'Equipo de computación',
                'fecha_compra' => now()->subMonths(8)->toDateString(),
                'valor_compra' => 1850.00,
                'depreciacion_acumulada' => 411.11,
                'estado' => 'En uso',
                'numero_de_serie' => 'DL5540-XK92',
                'ubicacion' => 'Oficina administrativa',
                'vida_util' => 3,
            ],
            [
                'nombre' => 'Escritorio ejecutivo',
                'referencia' => self::MARCA_DEMO.' AF-002',
                'categoria' => 'Mobiliario y equipo de oficina',
                'fecha_compra' => now()->subYears(2)->toDateString(),
                'valor_compra' => 420.00,
                'depreciacion_acumulada' => 84.00,
                'estado' => 'En uso',
                'ubicacion' => 'Sala de juntas',
                'vida_util' => 10,
            ],
            [
                'nombre' => 'Toyota Hilux 4x4',
                'referencia' => self::MARCA_DEMO.' AF-003',
                'categoria' => 'Vehículos',
                'fecha_compra' => now()->subYears(3)->toDateString(),
                'valor_compra' => 28500.00,
                'depreciacion_acumulada' => 21375.00,
                'estado' => 'En uso',
                'ubicacion' => 'Parqueo principal',
                'vida_util' => 4,
                'metadata' => [
                    'placa' => 'P123456',
                    'marca' => 'Toyota',
                    'modelo' => 'Hilux',
                    'anio' => 2022,
                    'color' => 'Blanco',
                ],
            ],
            [
                'nombre' => 'Aire acondicionado 18,000 BTU',
                'referencia' => self::MARCA_DEMO.' AF-004',
                'categoria' => 'Mobiliario y equipo de oficina',
                'fecha_compra' => now()->subMonths(14)->toDateString(),
                'valor_compra' => 680.00,
                'depreciacion_acumulada' => 113.33,
                'estado' => 'En reparación',
                'numero_de_serie' => 'AC18K-7781',
                'ubicacion' => 'Bodega',
                'vida_util' => 10,
            ],
            [
                'nombre' => 'Impresora multifuncional HP',
                'referencia' => self::MARCA_DEMO.' AF-005',
                'categoria' => 'Equipo de computación',
                'fecha_compra' => now()->subMonths(18)->toDateString(),
                'valor_compra' => 950.00,
                'depreciacion_acumulada' => 475.00,
                'estado' => 'En uso',
                'numero_de_serie' => 'HPMF-44102',
                'ubicacion' => 'Recepción',
                'vida_util' => 3,
            ],
            [
                'nombre' => 'Montacargas eléctrico',
                'referencia' => self::MARCA_DEMO.' AF-006',
                'categoria' => 'Maquinaria y equipo',
                'fecha_compra' => now()->subYears(5)->toDateString(),
                'valor_compra' => 12000.00,
                'depreciacion_acumulada' => 9000.00,
                'estado' => 'En uso',
                'numero_de_serie' => 'MC-EL-9081',
                'ubicacion' => 'Almacén',
                'vida_util' => 6.67,
            ],
            [
                'nombre' => 'Camión de reparto (baja)',
                'referencia' => self::MARCA_DEMO.' AF-007',
                'categoria' => 'Vehículos',
                'fecha_compra' => now()->subYears(8)->toDateString(),
                'fecha_retiro' => now()->subMonths(2)->toDateString(),
                'valor_compra' => 18000.00,
                'depreciacion_acumulada' => 18000.00,
                'estado' => 'Desechado',
                'ubicacion' => '—',
                'vida_util' => 4,
                'metadata' => [
                    'placa' => 'P987654',
                    'marca' => 'Isuzu',
                    'modelo' => 'NPR',
                    'anio' => 2016,
                    'color' => 'Rojo',
                ],
            ],
        ];

        $depreciacionService = app(DepreciacionService::class);

        foreach ($activos as $data) {
            $categoria = $categorias[$data['categoria']];
            $valorCompra = (float) $data['valor_compra'];

            $activo = Activo::withoutGlobalScopes()->create([
                'nombre' => $data['nombre'],
                'referencia' => $data['referencia'],
                'fecha_compra' => $data['fecha_compra'],
                'fecha_retiro' => $data['fecha_retiro'] ?? null,
                'estado' => $data['estado'],
                'id_categoria' => $categoria->id,
                'numero_de_serie' => $data['numero_de_serie'] ?? null,
                'descripcion' => 'Registro de demostración para pruebas del módulo de activos fijos.',
                'ubicacion' => $data['ubicacion'] ?? null,
                'vida_util' => $data['vida_util'],
                'valor_compra' => $valorCompra,
                'depreciacion_acumulada' => 0,
                'valor_en_libros' => $valorCompra,
                'valor_residual' => 0,
                'es_usado' => false,
                'fecha_inicio_depreciacion' => $data['fecha_compra'],
                'estado_registro' => 'activo',
                'metadata' => $data['metadata'] ?? null,
                'id_responsable' => $responsableId,
                'id_usuario' => $usuarioId,
                'id_sucursal' => $sucursalId,
                'id_empresa' => $idEmpresa,
            ]);

            $depreciacionService->generarCronograma($activo);
        }
    }
}
