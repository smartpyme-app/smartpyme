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
        $totalIngresos = $this->detalle->salario_devengado +
            $this->detalle->monto_horas_extra +
            $this->detalle->comisiones +
            $this->detalle->bonificaciones +
            $this->detalle->otros_ingresos;

        $totalDeducciones = $this->detalle->isss_empleado +
            $this->detalle->afp_empleado +
            $this->detalle->renta +
            $this->detalle->prestamos +
            $this->detalle->anticipos +
            $this->detalle->descuentos_judiciales +
            $this->detalle->otros_descuentos;

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