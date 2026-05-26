<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns;

use App\Exports\Contabilidad\Honduras\LibroComprasExport as LibroComprasHondurasExport;
use App\Exports\Contabilidad\Honduras\LibroVentasExport as LibroVentasHondurasExport;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use Maatwebsite\Excel\Facades\Excel;

/** Formato SAR (ventas/compras) usado por Honduras y otros países no SV/CR. */
trait HandlesLibrosIvaSar
{
    protected function ventasSarJson(BaseLibroIVARequest $request)
    {
        $export = new LibroVentasHondurasExport();
        $export->filter($request);
        $libroventas = $export->rowsForApi();

        $formato = $request->query('formato') ?? 'json';

        if ($formato === 'pdf') {
            $pdf = app('dompdf.wrapper')->loadView(
                'reportes.contabilidad.honduras.libro-ventas',
                [
                    'libroventas' => $libroventas,
                    'request' => $request,
                ]
            );
            $pdf->setPaper('Legal', 'landscape');

            return $pdf->stream('libro-ventas.pdf');
        }

        return response()->json($libroventas, 200);
    }

    protected function ventasSarExcel(BaseLibroIVARequest $request)
    {
        $consumidores = new LibroVentasHondurasExport();
        $consumidores->filter($request);

        return Excel::download($consumidores, 'Libro-ventas.xlsx');
    }

    protected function comprasSarJson(BaseLibroIVARequest $request)
    {
        $exportComprasHn = new LibroComprasHondurasExport();
        $exportComprasHn->filter($request);
        $librocompras = $exportComprasHn->rowsForApi();

        $formato = $request->query('formato') ?? 'json';

        if ($formato === 'pdf') {
            $pdf = app('dompdf.wrapper')->loadView(
                'reportes.contabilidad.honduras.libro-compras',
                [
                    'librocompras' => $librocompras,
                    'request' => $request,
                ]
            );
            $pdf->setPaper('US Letter', 'landscape');

            return $pdf->stream('libro-compras.pdf');
        }

        return response()->json($librocompras, 200);
    }

    protected function comprasSarExcel(BaseLibroIVARequest $request)
    {
        $compras = new LibroComprasHondurasExport();
        $compras->filter($request);

        return Excel::download($compras, 'Libro-compras.xlsx');
    }
}
