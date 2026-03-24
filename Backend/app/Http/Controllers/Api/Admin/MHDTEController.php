<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MH\AnularDTERequest;
use App\Http\Requests\MH\AnularDTESujetoExcluidoRequest;
use App\Http\Requests\MH\ConsultarDTERequest;
use App\Http\Requests\MH\EnviarDTERequest;
use App\Http\Requests\MH\GenerarContingenciaRequest;
use App\Http\Requests\MH\GenerarDTEAnuladoRequest;
use App\Http\Requests\MH\GenerarDTENotaCreditoRequest;
use App\Http\Requests\MH\GenerarDTERequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoCompraRequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoGastoRequest;
use App\Http\Requests\MH\GenerarDTEJSONRequest;
use App\Http\Requests\MH\GenerarDTEPDFRequest;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\ElSalvador\ElSalvadorDteService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryGate;
use Illuminate\Support\Facades\Auth;

class MHDTEController extends Controller
{
    public function __construct(
        private readonly ElSalvadorDteService $elSalvadorDte
    ) {}

    public function generarDTE(GenerarDTERequest $request)
    {
        $venta = Venta::where('id', $request->id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($venta->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTE($venta);
    }

    public function generarDTENotaCredito(GenerarDTENotaCreditoRequest $request)
    {
        $devolucion = DevolucionVenta::where('id', $request->id)->with('detalles', 'cliente', 'empresa', 'venta')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($devolucion->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTENotaCredito($devolucion);
    }

    public function generarDTESujetoExcluidoGasto(GenerarDTESujetoExcluidoGastoRequest $request)
    {
        $gasto = Gasto::where('id', $request->id)->with('proveedor', 'empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($gasto->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTESujetoExcluidoGasto($gasto);
    }

    public function generarDTESujetoExcluidoCompra(GenerarDTESujetoExcluidoCompraRequest $request)
    {
        $compra = Compra::where('id', $request->id)->with('detalles', 'proveedor', 'empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($compra->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTESujetoExcluidoCompra($compra);
    }

    public function generarContingencia(GenerarContingenciaRequest $request)
    {
        $ventas = Venta::whereIn('id', [$request->id])
            ->withAccessorRelations()
            ->with('detalles', 'empresa')
            ->get();
        $empresa = $ventas[0]->empresa;

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarContingencia($request);
    }

    public function generarDTEAnulado(GenerarDTEAnuladoRequest $request)
    {
        if ($request->tipo_dte == '05' || $request->tipo_dte == '06') {
            $registro = DevolucionVenta::where('id', $request->id)->with('empresa')->firstOrFail();
        } else {
            $registro = Venta::where('id', $request->id)->with('empresa')->firstOrFail();
        }

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($registro->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEAnulado($request);
    }

    public function generarDTEAnuladoSujetoExcluidoCompra(GenerarDTESujetoExcluidoCompraRequest $request)
    {
        $compra = Compra::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($compra->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEAnuladoSujetoExcluidoCompra($compra);
    }

    public function generarDTEAnuladoSujetoExcluidoGasto(GenerarDTESujetoExcluidoGastoRequest $request)
    {
        $gasto = Gasto::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($gasto->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEAnuladoSujetoExcluidoGasto($gasto);
    }

    public function generarTicket($id)
    {
        $venta = Venta::where('id', $id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($venta->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarTicket($venta);
    }

    public function anularDTE(AnularDTERequest $request)
    {
        $venta = Venta::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($venta->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->anularDTE($request);
    }

    public function anularDTESujetoExcluido(AnularDTESujetoExcluidoRequest $request)
    {
        $gasto = Gasto::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($gasto->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->anularDTESujetoExcluido($request);
    }

    public function generarDTEPDF($id, $tipo, GenerarDTEPDFRequest $request)
    {
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail(Auth::user()?->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEPDF($id, $tipo, $request);
    }

    public function generarDTEJSON($id, $tipo, GenerarDTEJSONRequest $request)
    {
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail(Auth::user()?->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEJSON($id, $tipo, $request);
    }

    public function enviarDTE(EnviarDTERequest $request)
    {
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail(Auth::user()?->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->enviarDTE($request);
    }

    public function consultarDTE(ConsultarDTERequest $request)
    {
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail(Auth::user()?->empresa)) {
            return $guard;
        }

        return response()->json($this->elSalvadorDte->consultarDTE($request));
    }
}
