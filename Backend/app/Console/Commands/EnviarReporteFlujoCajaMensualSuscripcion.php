<?php

namespace App\Console\Commands;

use App\Exports\Suscripciones\FlujoCajaMensualExport;
use App\Models\Suscripcion;
use App\Services\Suscripcion\FlujoCajaMensualAgregador;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class EnviarReporteFlujoCajaMensualSuscripcion extends Command
{
    protected $signature = 'suscripciones:reporte-flujo-caja-mensual {--mes= : Mes en formato YYYY-MM (por defecto: mes calendario actual)} {--dry-run : Generar Excel y no enviar correo}';

    protected $description = 'Reporte mensual de flujo de caja (Excel): pagos esperados del 1 al 15 y del 16 al fin de mes, por fecha de próximo pago.';

    public function handle(FlujoCajaMensualAgregador $agregador): int
    {
        $destinatario = config('constants.MAIL_REPORTE_FLUJO_CAJA_MENSUAL');
        if (! is_string($destinatario) || trim($destinatario) === '') {
            $this->error('Falta constants.MAIL_REPORTE_FLUJO_CAJA_MENSUAL.');

            return 1;
        }

        $mesStr = $this->option('mes');
        try {
            if ($mesStr) {
                $mesReferencia = Carbon::createFromFormat('Y-m', $mesStr)->startOfMonth()->startOfDay();
            } else {
                $mesReferencia = Carbon::now()->startOfMonth()->startOfDay();
            }
        } catch (\Throwable $e) {
            $this->error('Use --mes=YYYY-MM (ej. 2026-04).');

            return 1;
        }

        Carbon::setLocale('es');

        $inicio = $mesReferencia->copy()->startOfMonth()->startOfDay();
        $fin = $mesReferencia->copy()->endOfMonth()->endOfDay();

        $base = Suscripcion::query()
            ->with(['plan'])
            ->whereNotNull('fecha_proximo_pago')
            ->whereRaw('LOWER(TRIM(estado)) IN (?, ?, ?)', ['activo', 'pendiente', 'renovado'])
            ->whereBetween('fecha_proximo_pago', [$inicio, $fin]);

        $bloque1 = (clone $base)
            ->whereRaw('DAY(fecha_proximo_pago) BETWEEN 1 AND 15')
            ->orderBy('fecha_proximo_pago')
            ->get();

        $bloque2 = (clone $base)
            ->whereRaw('DAY(fecha_proximo_pago) >= 16')
            ->orderBy('fecha_proximo_pago')
            ->get();

        $filas1 = $agregador->construirFilasResumen($bloque1, $mesReferencia, 'Pagos del 1 al 15');
        $filas2 = $agregador->construirFilasResumen($bloque2, $mesReferencia, 'Pagos del 16 al fin de mes');

        $export = new FlujoCajaMensualExport($filas1, $filas2);
        $slugMes = $mesReferencia->format('Y-m');
        $filename = 'flujo-caja-suscripciones-'.$slugMes.'.xlsx';

        $dryRun = (bool) $this->option('dry-run');

        try {
            $binary = Excel::raw($export, ExcelFormat::XLSX);
        } catch (\Throwable $e) {
            Log::channel('suscripciones')->error('Reporte flujo caja: error generando Excel', [
                'mes' => $slugMes,
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return 1;
        }

        $this->info('Mes: '.$mesReferencia->translatedFormat('F Y').' | Bloque 1–15: '.$bloque1->count().' suscripción(es) | Bloque 16–fin: '.$bloque2->count().' suscripción(es).');

        if ($dryRun) {
            $this->warn('DRY-RUN: no se envió correo. Archivo: '.$filename);

            return 0;
        }

        $asunto = '[SmartPyme] Reporte mensual flujo de caja — '.$mesReferencia->translatedFormat('F Y');

        try {
            Mail::send('mails.reporte-flujo-caja-mensual', [
                'mesEtiqueta' => mb_convert_case($mesReferencia->translatedFormat('F Y'), MB_CASE_TITLE, 'UTF-8'),
                'generado' => Carbon::now()->format('d/m/Y H:i:s'),
            ], function ($message) use ($destinatario, $asunto, $binary, $filename) {
                $message->to($destinatario)->subject($asunto);
                $message->attachData($binary, $filename, [
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            });

            Log::channel('suscripciones')->info('Reporte flujo caja mensual enviado', [
                'mes' => $slugMes,
                'destinatario' => $destinatario,
            ]);

            $this->info('Correo enviado a '.$destinatario.' con adjunto '.$filename.'.');

            return 0;
        } catch (\Throwable $e) {
            Log::channel('suscripciones')->error('Reporte flujo caja: error enviando correo', [
                'mes' => $slugMes,
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return 1;
        }
    }
}
