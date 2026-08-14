<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\ZonaRestaurante;
use App\Support\Restaurante\MesasImportPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportarMesasRestaurante extends Command
{
    protected $signature = 'restaurante:importar-mesas
                            {archivo : Ruta al archivo xlsx}
                            {--empresa= : ID de empresa (obligatorio)}
                            {--dry-run : Solo validar y reportar, sin escribir}';

    protected $description = 'Importa mesas de restaurante desde Excel y las asigna a zonas existentes por nombre';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $empresaId = (int) $this->option('empresa');
        $archivo = $this->argument('archivo');

        if ($empresaId < 1) {
            $this->error('Debes indicar --empresa=ID');

            return 1;
        }

        if (! Empresa::where('id', $empresaId)->exists()) {
            $this->error("Empresa {$empresaId} no existe.");

            return 1;
        }

        if (! is_file($archivo)) {
            $this->error("Archivo no encontrado: {$archivo}");

            return 1;
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirán cambios.');
        }

        $rows = $this->leerFilas($archivo);
        if ($rows === []) {
            $this->warn('No hay filas de datos en el Excel.');

            return 0;
        }

        $zonas = ZonaRestaurante::where('id_empresa', $empresaId)
            ->where('activo', true)
            ->get(['id', 'nombre']);

        $indexed = MesasImportPlanner::indexZonas($zonas);
        if ($indexed['errors'] !== []) {
            foreach ($indexed['errors'] as $err) {
                $this->error($err);
            }

            return 1;
        }

        $existingKeys = [];
        Mesa::where('id_empresa', $empresaId)
            ->whereNotNull('zona_id')
            ->get(['zona_id', 'numero'])
            ->each(function ($m) use (&$existingKeys) {
                $existingKeys[$m->zona_id.'|'.$m->numero] = true;
            });

        $plan = MesasImportPlanner::plan($rows, $indexed['map'], $existingKeys);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['A crear', count($plan['crear'])],
                ['A omitir', count($plan['omitir'])],
                ['Errores', count($plan['errores'])],
            ]
        );

        if ($plan['errores'] !== []) {
            $this->error('Errores encontrados:');
            $this->table(
                ['Fila', 'Zona', 'Motivo'],
                array_map(fn ($e) => [$e['fila'], $e['zona'], $e['motivo']], $plan['errores'])
            );

            return 1;
        }

        if ($plan['crear'] !== []) {
            $sample = array_slice($plan['crear'], 0, 10);
            $this->info('Muestra a crear (máx. 10):');
            $this->table(
                ['Fila', 'Número', 'Capacidad', 'Zona', 'Orden'],
                array_map(
                    fn ($r) => [$r['fila'], $r['numero'], $r['capacidad'], $r['zona'], $r['orden']],
                    $sample
                )
            );
        }

        if ($dryRun) {
            $this->warn(sprintf(
                '[Dry-run] Se crearían %d mesas; se omitirían %d.',
                count($plan['crear']),
                count($plan['omitir'])
            ));

            return 0;
        }

        DB::transaction(function () use ($plan, $empresaId) {
            foreach ($plan['crear'] as $row) {
                Mesa::create([
                    'id_empresa' => $empresaId,
                    'id_sucursal' => null,
                    'numero' => $row['numero'],
                    'capacidad' => $row['capacidad'],
                    'zona_id' => $row['zona_id'],
                    'zona' => $row['zona'],
                    'orden' => $row['orden'],
                    'estado' => 'libre',
                    'activo' => true,
                ]);
            }
        });

        $this->info(sprintf(
            'Importación completa: %d mesas creadas, %d omitidas.',
            count($plan['crear']),
            count($plan['omitir'])
        ));

        return 0;
    }

    /**
     * @return list<array{fila:int, numero:mixed, capacidad:mixed, zona_nombre:mixed, orden:mixed}>
     */
    private function leerFilas(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);
        $rows = [];

        foreach ($raw as $i => $cols) {
            if ($i === 0) {
                continue;
            }

            $numero = $cols[0] ?? null;
            $capacidad = $cols[1] ?? null;
            $zonaNombre = $cols[3] ?? null;
            $orden = $cols[4] ?? null;

            if (($numero === null || $numero === '')
                && ($zonaNombre === null || $zonaNombre === '')
            ) {
                continue;
            }

            $rows[] = [
                'fila' => $i + 1,
                'numero' => $numero,
                'capacidad' => $capacidad,
                'zona_nombre' => $zonaNombre,
                'orden' => $orden,
            ];
        }

        return $rows;
    }
}
