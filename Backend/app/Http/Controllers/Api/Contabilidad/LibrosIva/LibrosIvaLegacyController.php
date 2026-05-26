<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Services\Contabilidad\LibrosIva\LibroIvaPaisResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Despacha rutas legacy (/libro-iva/consumidores, /compras, /retenciones)
 * al controlador del país correspondiente.
 */
class LibrosIvaLegacyController extends Controller
{
    public function __construct(
        private LibroIvaPaisResolver $libroIvaPaisResolver,
        private LibrosIvaSvController $librosIvaSv,
        private LibrosIvaHdController $librosIvaHd,
        private LibrosIvaGeneralController $librosIvaGeneral
    ) {}

    public function consumidores(BaseLibroIVARequest $request)
    {
        return match ($this->libroIvaPaisResolver->tipo()) {
            LibroIvaPaisResolver::TIPO_SV => $this->librosIvaSv->consumidores($request),
            LibroIvaPaisResolver::TIPO_HD => $this->librosIvaHd->ventas($request),
            default => $this->librosIvaGeneral->ventas($request),
        };
    }

    public function consumidoresLibroExport(BaseLibroIVARequest $request)
    {
        return match ($this->libroIvaPaisResolver->tipo()) {
            LibroIvaPaisResolver::TIPO_SV => $this->librosIvaSv->consumidoresLibroExport($request),
            LibroIvaPaisResolver::TIPO_HD => $this->librosIvaHd->ventasLibroExport($request),
            default => $this->librosIvaGeneral->ventasLibroExport($request),
        };
    }

    public function compras(BaseLibroIVARequest $request)
    {
        return match ($this->libroIvaPaisResolver->tipo()) {
            LibroIvaPaisResolver::TIPO_SV => $this->librosIvaSv->compras($request),
            LibroIvaPaisResolver::TIPO_HD => $this->librosIvaHd->compras($request),
            default => $this->librosIvaGeneral->compras($request),
        };
    }

    public function comprasLibroExport(BaseLibroIVARequest $request)
    {
        return match ($this->libroIvaPaisResolver->tipo()) {
            LibroIvaPaisResolver::TIPO_SV => $this->librosIvaSv->comprasLibroExport($request),
            LibroIvaPaisResolver::TIPO_HD => $this->librosIvaHd->comprasLibroExport($request),
            default => $this->librosIvaGeneral->comprasLibroExport($request),
        };
    }

    public function retenciones(Request $request): JsonResponse
    {
        return match ($this->libroIvaPaisResolver->tipo()) {
            LibroIvaPaisResolver::TIPO_HD => $this->librosIvaHd->retenciones($request),
            default => $this->librosIvaGeneral->retenciones($request),
        };
    }
}
