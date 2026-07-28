<?php

namespace App\Console\Commands;

use App\Exports\Suscripciones\BajasMensualesExport;
use App\Models\Suscripcion;
use App\Models\SuscripcionBaja;
use App\Services\Suscripcion\BajasMensualesAgregador;
use App\Services\Suscripcion\RegistrarSuscripcionBaja;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class EnviarReporteBajasMensualSuscripcion extends Command
{
    protected $signature = 'suscripciones:reporte-bajas-mensual
        {--mes= : Mes en formato YYYY-MM (por defecto: mes calendario anterior)}
        {--dry-run : Generar Excel y no enviar correo}
        {--backfill : Importar cancelaciones existentes con fecha_cancelacion sin fila en histórico}';

    protected $description = 'Reporte mensual de bajas de suscripción (HTML + Excel): mes, histórico 12m y proyección resto del año.';

    public function handle(BajasMensualesAgregador $agregador, RegistrarSuscripcionBaja $registrar): int
    {
        $destinatario = config('constants.MAIL_REPORTE_BAJAS_SUSCRIPCION');
        if (! is_string($destinatario) || trim($destinatario) === '') {
            $this->error('Falta constants.MAIL_REPORTE_BAJAS_SUSCRIPCION.');

            return 1;
        }

        if ($this->option('backfill')) {
            $n = $this->backfillCancelaciones($registrar);
            $this->info("Backfill: {$n} cancelación(es) importada(s).");
        }

        $mesStr = $this->option('mes');
        try {
            if ($mesStr) {
                $mesReferencia = Carbon::createFromFormat('Y-m', $mesStr)->startOfMonth()->startOfDay();
            } else {
                // Día 1 del mes: reporta el mes calendario anterior.
                $mesReferencia = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
            }
        } catch (\Throwable $e) {
            $this->error('Use --mes=YYYY-MM (ej. 2026-01).');

            return 1;
        }

        Carbon::setLocale('es');
        $datos = $agregador->construir($mesReferencia);
        $mesEtiqueta = mb_convert_case($mesReferencia->translatedFormat('F Y'), MB_CASE_TITLE, 'UTF-8');

        $filasDetalle = $this->filasDetalleExcel($datos['detalle']);
        $filasHistorico = $this->filasHistoricoExcel($datos['historico_12m']);
        $filasProyeccion = $this->filasProyeccionExcel($datos['proyeccion']);

        $export = new BajasMensualesExport($filasDetalle, $filasHistorico, $filasProyeccion);
        $slugMes = $mesReferencia->format('Y-m');
        $filename = 'bajas-suscripciones-'.$slugMes.'.xlsx';
        $dryRun = (bool) $this->option('dry-run');

        try {
            $binary = Excel::raw($export, ExcelFormat::XLSX);
        } catch (\Throwable $e) {
            Log::channel('suscripciones')->error('Reporte bajas: error generando Excel', [
                'mes' => $slugMes,
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return 1;
        }

        $r = $datos['resumen_mes'];
        $this->info(sprintf(
            'Mes: %s | Bajas: %d | Mensuales: $%s | Trimestrales: $%s | Anuales: $%s',
            $mesEtiqueta,
            $r['total'],
            number_format($r['mensuales'], 2),
            number_format($r['trimestrales'], 2),
            number_format($r['anuales'], 2)
        ));

        if ($dryRun) {
            $this->warn('DRY-RUN: no se envió correo. Archivo: '.$filename);

            return 0;
        }

        $asunto = '[SmartPyme] Reporte mensual bajas de suscripción — '.$mesEtiqueta;

        try {
            Mail::send('mails.reporte-bajas-mensual', [
                'mesEtiqueta' => $mesEtiqueta,
                'generado' => Carbon::now()->format('d/m/Y H:i:s'),
                'resumen' => $r,
                'historico' => $datos['historico_12m'],
                'proyeccion' => $datos['proyeccion'],
                'anio' => (int) $mesReferencia->year,
            ], function ($message) use ($destinatario, $asunto, $binary, $filename) {
                $message->to($destinatario)->subject($asunto);
                $message->attachData($binary, $filename, [
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            });

            Log::channel('suscripciones')->info('Reporte bajas mensual enviado', [
                'mes' => $slugMes,
                'destinatario' => $destinatario,
                'total_bajas' => $r['total'],
            ]);

            $this->info('Correo enviado a '.$destinatario.' con adjunto '.$filename.'.');

            return 0;
        } catch (\Throwable $e) {
            Log::channel('suscripciones')->error('Reporte bajas: error enviando correo', [
                'mes' => $slugMes,
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function backfillCancelaciones(RegistrarSuscripcionBaja $registrar): int
    {
        $cancelado = config('constants.ESTADO_SUSCRIPCION_CANCELADO');
        $ya = SuscripcionBaja::query()
            ->where('motivo', SuscripcionBaja::MOTIVO_CANCELACION_VOLUNTARIA)
            ->pluck('suscripcion_id')
            ->unique()
            ->all();

        $query = Suscripcion::query()
            ->with(['empresa', 'plan'])
            ->whereNotNull('fecha_cancelacion')
            ->where(function ($q) use ($cancelado) {
                $q->where('estado', $cancelado)
                    ->orWhereRaw('LOWER(TRIM(estado)) = ?', ['cancelado']);
            });

        if ($ya !== []) {
            $query->whereNotIn('id', $ya);
        }

        $count = 0;
        foreach ($query->cursor() as $suscripcion) {
            $fecha = Carbon::parse($suscripcion->fecha_cancelacion);
            $baja = $registrar->registrar(
                $suscripcion,
                SuscripcionBaja::MOTIVO_CANCELACION_VOLUNTARIA,
                $fecha,
                $suscripcion->motivo_cancelacion
            );
            if ($baja && (float) $baja->monto <= 0) {
                Log::channel('suscripciones')->warning('Backfill baja con monto 0', [
                    'suscripcion_id' => $suscripcion->id,
                    'empresa_id' => $suscripcion->empresa_id,
                ]);
            }
            if ($baja) {
                $count++;
            }
        }

        return $count;
    }

    /** @param \Illuminate\Support\Collection<int, SuscripcionBaja> $detalle */
    private function filasDetalleExcel($detalle): array
    {
        $filas = [];
        $filas[] = ['DETALLE BAJAS DEL MES'];
        $filas[] = ['Empresa', 'Motivo', 'Tipo plan', 'Monto (USD)', 'Fecha baja', 'Plan', 'Motivo cancelación', 'Empresa ID', 'Suscripción ID'];
        foreach ($detalle as $b) {
            $filas[] = [
                $b->empresa_nombre ?? '',
                $b->motivo,
                $b->tipo_plan ?? '',
                round((float) $b->monto, 2),
                $b->fecha_baja ? Carbon::parse($b->fecha_baja)->format('Y-m-d H:i') : '',
                $b->plan_nombre ?? '',
                $b->motivo_cancelacion ?? '',
                $b->empresa_id,
                $b->suscripcion_id,
            ];
        }
        if ($detalle->isEmpty()) {
            $filas[] = ['(sin bajas en el mes)'];
        }

        return $filas;
    }

    /** @param array<int, array<string, mixed>> $historico */
    private function filasHistoricoExcel(array $historico): array
    {
        $filas = [];
        $filas[] = ['HISTORICO ULTIMOS 12 MESES'];
        $filas[] = ['Mes', 'Bajas', 'Mensuales (USD)', 'Trimestrales (USD)', 'Anuales (USD)'];
        foreach ($historico as $h) {
            $filas[] = [
                $h['etiqueta'],
                $h['total'],
                $h['mensuales'],
                $h['trimestrales'],
                $h['anuales'],
            ];
        }

        return $filas;
    }

    /** @param array<string, mixed> $proyeccion */
    private function filasProyeccionExcel(array $proyeccion): array
    {
        $filas = [];
        $filas[] = ['PROYECCION RESTO DEL AÑO'];
        $filas[] = ['MRR mensual perdido YTD', $proyeccion['mrr_mensual_ytd']];
        $filas[] = ['Meses restantes', $proyeccion['meses_restantes']];
        $filas[] = ['Impacto mensual restante', $proyeccion['impacto_mensual_restante']];
        $filas[] = ['Trimestrales YTD', $proyeccion['trimestral_ytd']];
        $filas[] = ['Impacto trimestral restante (aprox.)', $proyeccion['impacto_trimestral_restante']];
        $filas[] = ['ARR anual perdido YTD', $proyeccion['anual_ytd']];
        $filas[] = ['Total orientativo', $proyeccion['total_orientativo']];
        $filas[] = [];
        $filas[] = ['Mes proyectado', 'MRR mensual perdido', 'Nota'];
        foreach ($proyeccion['filas_meses'] as $fila) {
            $filas[] = [
                $fila['etiqueta'],
                $fila['mensuales'],
                $fila['nota'],
            ];
        }
        if ($proyeccion['filas_meses'] === []) {
            $filas[] = ['(sin meses restantes)'];
        }

        return $filas;
    }
}
