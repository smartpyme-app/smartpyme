<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BoletaPagoMailable extends Mailable
{
    use Queueable, SerializesModels;

    protected $detalle;
    protected $planilla;
    protected $empresa;
    protected $periodo;

    public function __construct($detalle, $planilla, $empresa, $periodo)
    {
        $this->detalle = $detalle;
        $this->planilla = $planilla;
        $this->empresa = $empresa;
        $this->periodo = $periodo;
    }

    public function build()
    {
        // Calcular totales
        $totalIngresos = (float)($this->detalle->salario_devengado ?? $this->detalle->salario_base) +
            (float)($this->detalle->monto_horas_extra ?? 0) +
            (float)($this->detalle->comisiones ?? 0) +
            (float)($this->detalle->bonificaciones ?? 0) +
            (float)($this->detalle->otros_ingresos ?? 0) +
            (float)($this->detalle->abonos ?? 0);

        $totalDeducciones = (float)($this->detalle->isss_empleado ?? 0) +
            (float)($this->detalle->afp_empleado ?? 0) +
            (float)($this->detalle->renta ?? 0) +
            (float)($this->detalle->prestamos ?? 0) +
            (float)($this->detalle->anticipos ?? 0) +
            (float)($this->detalle->descuentos_judiciales ?? 0) +
            (float)($this->detalle->otros_descuentos ?? 0);

        // Generar PDF
        $pdf = app('dompdf.wrapper')->loadView('pdf.boleta-individual', [
            'detalle' => $this->detalle,
            'totalIngresos' => $totalIngresos,
            'totalDeducciones' => $totalDeducciones,
            'periodo' => $this->periodo
        ]);

        return $this->view('mails.boleta-pago')
            ->subject('Boleta de Pago - ' . $this->planilla->codigo)
            ->attachData($pdf->output(), 'boleta-' . $this->detalle->empleado->codigo . '.pdf', [
                'mime' => 'application/pdf'
            ])
            ->with([
                'empleado' => $this->detalle->empleado,
                'planilla' => $this->planilla,
                'empresa' => $this->empresa
            ]);
    }
}