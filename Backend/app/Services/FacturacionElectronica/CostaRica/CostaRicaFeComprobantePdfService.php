<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use App\Support\FacturacionElectronica\CostaRicaFeComprobantePdfAggregates;
use App\Support\FacturacionElectronica\CostaRicaFeDteDocumento;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * PDF representación gráfica FE-CR (vista {@see resources/views/reportes/facturacion/FE-CR-Comprobante.blade.php}).
 * Centraliza la lógica compartida entre {@see \App\Http\Controllers\Api\Admin\MHDTEController} y el correo al cliente.
 */
final class CostaRicaFeComprobantePdfService
{
    /**
     * Datos para la vista Blade del comprobante CR.
     *
     * @return array{registro: Venta|DevolucionVenta|Compra|Gasto, documento: array, clave: string, titulo: string, tipoDteCodigo: string}
     */
    public function datosVistaParaPdf(int $id, string $tipo, ?string $queryTipo, int $idEmpresaUsuario): array
    {
        $id = (int) $id;
        $registro = null;
        $documento = [];
        $clave = '';
        $titulo = 'Comprobante electrónico';
        $tipoDteRuta = (string) $tipo;

        if (in_array($tipo, ['01', '04', '11'], true)) {
            $registro = Venta::query()->with(['empresa', 'cliente'])->findOrFail($id);
            $this->assertMismoEmpresa($registro->id_empresa, $idEmpresaUsuario);
            $dte = $registro->dte;
            if (! CostaRicaFeDteDocumento::tieneComprobanteCr($dte)) {
                throw new RuntimeException('No hay comprobante electrónico Costa Rica para este registro.');
            }
            $documento = CostaRicaFeDteDocumento::documentoParaPdf($dte);
            $clave = (string) ($registro->codigo_generacion ?? '');
            $titulo = match ($tipo) {
                '04' => 'Tiquete electrónico',
                '11' => 'Factura de exportación',
                default => 'Factura electrónica',
            };
        } elseif ($tipo === '02') {
            $venta = Venta::query()->with(['empresa', 'cliente'])->findOrFail($id);
            $this->assertMismoEmpresa($venta->id_empresa, $idEmpresaUsuario);
            $registro = $this->devolucionNotaDebitoPorVenta($venta);
            $dte = $registro->dte;
            if (! CostaRicaFeDteDocumento::tieneComprobanteCr($dte)) {
                $bloqueNd = is_array($venta->dte) ? ($venta->dte['cr']['nota_debito'] ?? null) : null;
                if (is_array($bloqueNd) && CostaRicaFeDteDocumento::tieneComprobanteCr(['pais' => 'CR', 'documento' => $bloqueNd['documento'] ?? null, 'cr' => $bloqueNd])) {
                    $documento = CostaRicaFeDteDocumento::documentoParaPdf(['pais' => 'CR'], $bloqueNd);
                } else {
                    throw new RuntimeException('No hay nota de débito electrónica asociada.');
                }
            } else {
                $documento = CostaRicaFeDteDocumento::documentoParaPdf($dte);
            }
            $clave = (string) ($registro->codigo_generacion ?? '');
            $titulo = 'Nota de débito electrónica';
        } elseif (in_array($tipo, ['03', '05', '06'], true)) {
            $registro = DevolucionVenta::query()->with(['empresa', 'cliente'])->findOrFail($id);
            $this->assertMismoEmpresa($registro->id_empresa, $idEmpresaUsuario);
            $dte = $registro->dte;
            if (! CostaRicaFeDteDocumento::tieneComprobanteCr($dte)) {
                throw new RuntimeException('No hay comprobante electrónico Costa Rica para esta devolución.');
            }
            $documento = CostaRicaFeDteDocumento::documentoParaPdf($dte);
            $clave = (string) ($registro->codigo_generacion ?? '');
            $titulo = 'Nota de crédito electrónica';
        } elseif (($tipo === '08' && $queryTipo === 'compra') || ($tipo === '14' && $queryTipo === 'compra')) {
            $registro = Compra::query()->with(['empresa', 'proveedor'])->findOrFail($id);
            $this->assertMismoEmpresa($registro->id_empresa, $idEmpresaUsuario);
            $dte = $registro->dte;
            if (! CostaRicaFeDteDocumento::tieneComprobanteCr($dte)) {
                throw new RuntimeException('No hay comprobante electrónico Costa Rica para esta compra.');
            }
            if ((string) ($registro->tipo_dte ?? '') !== '08') {
                throw new RuntimeException('Esta compra no tiene factura electrónica de compras (FEC, tipo 08).');
            }
            $documento = CostaRicaFeDteDocumento::documentoParaPdf($dte);
            $clave = (string) ($registro->codigo_generacion ?? '');
            $titulo = 'Factura electrónica de compra';
            $tipoDteRuta = '08';
        } elseif (($tipo === '08' && $queryTipo === 'gasto') || ($tipo === '14' && $queryTipo === 'gasto')) {
            $registro = Gasto::query()->with(['empresa', 'proveedor'])->findOrFail($id);
            $this->assertMismoEmpresa($registro->id_empresa, $idEmpresaUsuario);
            $dte = $registro->dte;
            if (! CostaRicaFeDteDocumento::tieneComprobanteCr($dte)) {
                throw new RuntimeException('No hay comprobante electrónico Costa Rica para este egreso.');
            }
            if ((string) ($registro->tipo_dte ?? '') !== '08') {
                throw new RuntimeException('Este egreso no tiene factura electrónica de compras (FEC, tipo 08).');
            }
            $documento = CostaRicaFeDteDocumento::documentoParaPdf($dte);
            $clave = (string) ($registro->codigo_generacion ?? '');
            $titulo = 'Factura electrónica de compra';
            $tipoDteRuta = '08';
        } else {
            throw new RuntimeException('Tipo de documento no disponible para PDF en FE Costa Rica.');
        }

        if ($documento === []) {
            throw new RuntimeException('No se pudo obtener el contenido del comprobante para la representación gráfica.');
        }

        if (
            $registro instanceof Venta
            && in_array($tipo, ['01', '11'], true)
            && trim((string) ($registro->nombre_documento ?? '')) === 'Factura de exportación'
        ) {
            $tipoDteRuta = '11';
            $titulo = 'Factura de exportación';
        }

        return [
            'registro' => $registro,
            'documento' => $documento,
            'clave' => $clave,
            'titulo' => $titulo,
            'tipoDteCodigo' => $tipoDteRuta,
        ];
    }

    /**
     * @param  array{registro: mixed, documento: array, clave: string, titulo: string, tipoDteCodigo: string}  $datos
     */
    public function pdfStreamResponse(array $datos): Response
    {
        $moneda = strtoupper((string) (($datos['documento']['currency']['currency_code'] ?? 'CRC')));
        $feCrPdf = CostaRicaFeComprobantePdfAggregates::fromDocument(
            $datos['documento'],
            (string) ($datos['clave'] ?? ''),
            $moneda
        );
        $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.FE-CR-Comprobante', [
            'registro' => $datos['registro'],
            'documento' => $datos['documento'],
            'clave' => $datos['clave'],
            'titulo' => $datos['titulo'],
            'tipoDteCodigo' => $datos['tipoDteCodigo'],
            'feCrPdf' => $feCrPdf,
        ]);
        $pdf->setPaper('US Letter', 'portrait');
        $nombre = $datos['clave'] !== '' ? $datos['clave'] : 'comprobante-cr';

        return $pdf->stream($nombre.'.pdf');
    }

    /**
     * @param  array{registro: mixed, documento: array, clave: string, titulo: string, tipoDteCodigo: string}  $datos
     */
    public function pdfBinary(array $datos): string
    {
        $moneda = strtoupper((string) (($datos['documento']['currency']['currency_code'] ?? 'CRC')));
        $feCrPdf = CostaRicaFeComprobantePdfAggregates::fromDocument(
            $datos['documento'],
            (string) ($datos['clave'] ?? ''),
            $moneda
        );
        $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.FE-CR-Comprobante', [
            'registro' => $datos['registro'],
            'documento' => $datos['documento'],
            'clave' => $datos['clave'],
            'titulo' => $datos['titulo'],
            'tipoDteCodigo' => $datos['tipoDteCodigo'],
            'feCrPdf' => $feCrPdf,
        ]);
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->output();
    }

    private function devolucionNotaDebitoPorVenta(Venta $venta): DevolucionVenta
    {
        $dev = DevolucionVenta::query()
            ->with(['empresa', 'cliente'])
            ->where('id_venta', $venta->id)
            ->where('enable', true)
            ->whereNotNull('codigo_generacion')
            ->whereHas('documento', function ($q): void {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%nota%'])
                    ->where(function ($q2): void {
                        $q2->whereRaw('LOWER(nombre) LIKE ?', ['%débito%'])
                            ->orWhereRaw('LOWER(nombre) LIKE ?', ['%debito%']);
                    });
            })
            ->orderByDesc('id')
            ->first();

        if ($dev === null) {
            throw new RuntimeException('No hay nota de débito electrónica asociada.');
        }

        return $dev;
    }

    private function assertMismoEmpresa(int $registroEmpresaId, int $userEmpresaId): void
    {
        if ((int) $registroEmpresaId !== (int) $userEmpresaId) {
            throw new RuntimeException('No autorizado.');
        }
    }
}
