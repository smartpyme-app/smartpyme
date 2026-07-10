<?php

namespace App\Console\Commands;

use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ActualizarCorreoEmisorDteSagrivet extends Command
{
    protected $signature = 'dte:actualizar-correo-emisor-sagrivet
                            {--dry-run : Solo mostrar qué se actualizaría, sin ejecutar}
                            {--id-empresa=675 : ID de la empresa (Sagrivet)}
                            {--desde=2026-05-01 : Fecha mínima inclusive (Y-m-d)}
                            {--correo-anterior=vetbanimal@gmail.com : Correo del emisor a reemplazar}
                            {--correo-nuevo=sagrivet.contabilidad@gmail.com : Correo del emisor destino}';

    protected $description = 'Actualiza el correo del emisor en dte y dte_invalidacion de ventas de Sagrivet (empresa 675) desde mayo';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $idEmpresa = (int) $this->option('id-empresa');
        $correoAnterior = strtolower(trim((string) $this->option('correo-anterior')));
        $correoNuevo = strtolower(trim((string) $this->option('correo-nuevo')));

        try {
            $desde = Carbon::parse((string) $this->option('desde'))->format('Y-m-d');
        } catch (\Throwable $e) {
            $this->error('Fecha inválida en --desde. Usa formato Y-m-d (ej. 2026-05-01).');

            return 1;
        }

        if ($correoAnterior === '' || $correoNuevo === '') {
            $this->error('Los correos anterior y nuevo no pueden estar vacíos.');

            return 1;
        }

        if ($correoAnterior === $correoNuevo) {
            $this->error('El correo anterior y el nuevo no pueden ser iguales.');

            return 1;
        }

        $empresa = DB::table('empresas')->where('id', $idEmpresa)->first();
        if (!$empresa) {
            $this->error("No se encontró la empresa con id={$idEmpresa}.");

            return 1;
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se aplicarán cambios.');
        }

        $this->info("Empresa: {$empresa->nombre} (id={$idEmpresa})");
        $this->info("Rango: ventas con fecha >= {$desde}");
        $this->info("Reemplazo: {$correoAnterior} -> {$correoNuevo}");

        $disk = config('dte.disk', 's3');
        $stats = [
            'revisados' => 0,
            'actualizados_dte' => 0,
            'actualizados_invalidacion' => 0,
            'omitidos' => 0,
            'errores' => 0,
        ];

        $query = Venta::query()
            ->withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('fecha', '>=', $desde)
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('dte')->where('dte', '!=', '')
                        ->orWhereNotNull('dte_s3_key');
                })->orWhere(function ($w) {
                    $w->whereNotNull('dte_invalidacion')->where('dte_invalidacion', '!=', '')
                        ->orWhereNotNull('dte_invalidacion_s3_key');
                });
            })
            ->orderBy('id');

        $total = (clone $query)->count();
        $this->info("Ventas a revisar: {$total}");

        $query->chunkById(50, function ($ventas) use (
            &$stats,
            $dryRun,
            $disk,
            $correoAnterior,
            $correoNuevo
        ) {
            foreach ($ventas as $venta) {
                $stats['revisados']++;

                foreach ([
                    ['json' => 'dte', 'key' => 'dte_s3_key', 'label' => 'dte'],
                    ['json' => 'dte_invalidacion', 'key' => 'dte_invalidacion_s3_key', 'label' => 'dte_invalidacion'],
                ] as $column) {
                    $result = $this->processColumn(
                        $venta,
                        $column['json'],
                        $column['key'],
                        $disk,
                        $correoAnterior,
                        $correoNuevo,
                        $dryRun
                    );

                    if ($result === 'updated') {
                        if ($column['label'] === 'dte') {
                            $stats['actualizados_dte']++;
                        } else {
                            $stats['actualizados_invalidacion']++;
                        }
                        $this->line("Actualizado venta#{$venta->id} [{$column['label']}]");
                    } elseif ($result === 'skipped') {
                        $stats['omitidos']++;
                    } elseif ($result === 'error') {
                        $stats['errores']++;
                    }
                }
            }
        });

        $this->newLine();
        $this->info('Resumen:');
        $this->line("  Ventas revisadas: {$stats['revisados']}");
        $this->line("  dte actualizados: {$stats['actualizados_dte']}");
        $this->line("  dte_invalidacion actualizados: {$stats['actualizados_invalidacion']}");
        $this->line("  Omitidos (sin correo objetivo): {$stats['omitidos']}");
        $this->line("  Errores: {$stats['errores']}");

        if ($dryRun) {
            $this->warn('Dry-run finalizado. Ejecuta sin --dry-run para aplicar los cambios.');
        }

        return $stats['errores'] > 0 ? 1 : 0;
    }

    /**
     * @return 'updated'|'skipped'|'absent'|'error'
     */
    protected function processColumn(
        Venta $venta,
        string $jsonCol,
        string $keyCol,
        string $disk,
        string $correoAnterior,
        string $correoNuevo,
        bool $dryRun
    ): string {
        $raw = $venta->getRawOriginal($jsonCol);
        $s3Key = $venta->getRawOriginal($keyCol);

        if (($raw === null || $raw === '') && empty($s3Key)) {
            return 'absent';
        }

        $decoded = null;
        $source = 'db';

        if ($raw !== null && $raw !== '') {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        } elseif (!empty($s3Key)) {
            $source = 's3';
            $decoded = $this->readJsonFromS3($disk, $s3Key);
            if ($decoded === null) {
                $this->error("No se pudo leer S3 venta#{$venta->id} col={$jsonCol} key={$s3Key}");

                return 'error';
            }
        }

        if (!is_array($decoded)) {
            $this->warn("JSON inválido venta#{$venta->id} col={$jsonCol}");

            return 'error';
        }

        $correoActual = strtolower(trim((string) ($decoded['emisor']['correo'] ?? '')));
        if ($correoActual === '') {
            return 'skipped';
        }

        if ($correoActual === $correoNuevo) {
            return 'skipped';
        }

        if ($correoActual !== $correoAnterior) {
            $this->line("Omitido venta#{$venta->id} [{$jsonCol}]: correo emisor={$correoActual} (no coincide con {$correoAnterior})");

            return 'skipped';
        }

        $decoded['emisor']['correo'] = $correoNuevo;
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $this->error("No se pudo serializar JSON venta#{$venta->id} col={$jsonCol}");

            return 'error';
        }

        if ($dryRun) {
            $this->line("[dry-run] venta#{$venta->id} [{$jsonCol}] ({$source}): {$correoAnterior} -> {$correoNuevo}");

            return 'updated';
        }

        try {
            if ($source === 's3') {
                Storage::disk($disk)->put($s3Key, $encoded, [
                    'visibility' => 'private',
                    'ContentType' => 'application/json',
                ]);
            } else {
                DB::table('ventas')->where('id', $venta->id)->update([
                    $jsonCol => $encoded,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('dte:actualizar-correo-emisor-sagrivet falló', [
                'venta_id' => $venta->id,
                'column' => $jsonCol,
                'source' => $source,
                'message' => $e->getMessage(),
            ]);
            $this->error("Error venta#{$venta->id} col={$jsonCol}: " . $e->getMessage());

            return 'error';
        }

        return 'updated';
    }

    protected function readJsonFromS3(string $disk, string $key): ?array
    {
        try {
            if (!Storage::disk($disk)->exists($key)) {
                return null;
            }
            $raw = Storage::disk($disk)->get($key);
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::error('dte:actualizar-correo-emisor-sagrivet lectura S3', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
