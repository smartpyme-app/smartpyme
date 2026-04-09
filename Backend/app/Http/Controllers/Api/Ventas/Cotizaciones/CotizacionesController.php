<?php

namespace App\Http\Controllers\Api\Ventas\Cotizaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registros\Cliente;
use App\Models\Ventas\Clientes\Cliente as ClienteVenta;
use App\Models\Ventas\Venta as Cotizacion;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Detalle;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use JWTAuth;
use App\Exports\CotizacionesExport;
use App\Models\CotizacionVenta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Constants\CotizacionConstants;
use App\Http\Requests\Ventas\Cotizaciones\StoreCotizacionRequest;
use App\Http\Requests\Ventas\Cotizaciones\FacturacionCotizacionRequest;


class CotizacionesController extends Controller
{

    /**
     * Preferencias de cotización desde custom_empresa (PDF).
     */
    private function cotizacionPdfViewData(Cotizacion $venta): array
    {
        $custom = ($venta->empresa && is_array($venta->empresa->custom_empresa))
            ? $venta->empresa->custom_empresa
            : [];
        $cfg = isset($custom['configuraciones']) && is_array($custom['configuraciones'])
            ? $custom['configuraciones']
            : [];

        return [
            'cotizacion_mostrar_descripcion' => array_key_exists('cotizacion_mostrar_descripcion', $cfg)
                ? (bool) $cfg['cotizacion_mostrar_descripcion']
                : true,
            'cotizacion_mostrar_imagenes_productos' => !empty($cfg['cotizacion_mostrar_imagenes_productos']),
        ];
    }

    /**
     * Usuario con rol Ventas / Ventas Limitado (listado y export: solo cotizaciones propias, como en ventas).
     */
    private function esUsuarioRolVentasCotizaciones(): bool
    {
        $user = Auth::user();

        return $user && ($user->tipo === 'Ventas' || $user->tipo === 'Ventas Limitado');
    }

    /**
     * Bloqueo extra (facturar/editar/detalles/cambiar estado): solo si la empresa activó la opción en Mi cuenta.
     */
    private function aplicarRestriccionesCotizacionesVendedores(): bool
    {
        $user = Auth::user();
        if (! $user || ($user->tipo !== 'Ventas' && $user->tipo !== 'Ventas Limitado')) {
            return false;
        }

        $empresa = Empresa::find($user->id_empresa);

        return $empresa && (bool) $empresa->getCustomConfigValue('configuraciones', 'bloquear_cotizaciones_vendedores', false);
    }

    public function index(Request $request) {

        $ordenes = Cotizacion::when($this->esUsuarioRolVentasCotizaciones(), function ($query) {
                            $query->where('id_usuario', Auth::id());
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when(! $this->esUsuarioRolVentasCotizaciones() && $request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->id_canal, function($query) use ($request){
                            return $query->where('id_canal', $request->id_canal);
                        })
                        ->when($request->id_documento, function($query) use ($request){
                            return $query->where('id_documento', $request->id_documento);
                        })
                        ->when($request->id_proyecto, function($query) use ($request){
                            return $query->where('id_proyecto', $request->id_proyecto);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                        ->when($request->buscador, fn ($query) => $this->aplicarFiltroBuscadorCotizaciones($query, (string) $request->buscador))
                    ->where('cotizacion', 1)
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($ordenes, 200);
    }

    /**
     * Aplica el filtro de búsqueda por cliente (FULLTEXT: nombre, apellido, ncr, nit, nombre_empresa),
     * correlativo (LIKE), y ventas (FULLTEXT: num_orden, observaciones, forma_pago, estado, numero_control).
     */
    private function aplicarFiltroBuscadorCotizaciones($query, string $termino)
    {
        $termino = trim(preg_replace('/\s+/', ' ', $termino));
        if ($termino === '') {
            return $query;
        }

        $buscador = '%' . $termino . '%';
        $palabras = array_values(array_filter(explode(' ', $termino), fn ($p) => $p !== ''));

        $matchClientes = count($palabras) > 1
            ? implode(' ', array_map(fn ($p) => '+' . preg_replace('/[+\-<>()~*"]/', '', $p), $palabras))
            : $termino;
        $clienteIds = ClienteVenta::query()
            ->whereRaw(
                'MATCH(clientes.nombre, clientes.apellido, clientes.nombre_empresa, clientes.nit, clientes.ncr) AGAINST(? IN ' . (count($palabras) > 1 ? 'BOOLEAN' : 'NATURAL LANGUAGE') . ' MODE)',
                [$matchClientes]
            )
            ->limit(5000)
            ->pluck('id');

        return $query->where(function ($q) use ($buscador, $clienteIds, $termino) {
            if ($clienteIds->isNotEmpty()) {
                $q->whereIn('id_cliente', $clienteIds);
            }
            $q->orWhere('correlativo', 'like', $buscador)
                ->orWhereRaw(
                    'MATCH(ventas.num_orden, ventas.observaciones, ventas.forma_pago, ventas.estado, ventas.numero_control) AGAINST(? IN NATURAL LANGUAGE MODE)',
                    [$termino]
                );
        });
    }

    public function read($id) {

        $orden = Cotizacion::where('id', $id)->with('cliente', 'detalles')->firstOrFail();
        if ($this->esUsuarioRolVentasCotizaciones() && (int) $orden->id_usuario !== (int) Auth::id()) {
            abort(403, 'No autorizado');
        }
        return Response()->json($orden, 200);
    }

    public function search($txt)
    {

        $ordenes = Cotizacion::when($this->esUsuarioRolVentasCotizaciones(), function ($query) {
                                $query->where('id_usuario', Auth::id());
                            })
                                ->where('cotizacion', 1)
                                ->where(function ($q) use ($txt) {
                                    $q->whereHas('cliente', function ($cq) use ($txt) {
                                        $cq->where('nombre', 'like', '%' . $txt . '%');
                                    })->orWhere('estado', 'like', '%' . $txt . '%');
                                })
                                ->with('cliente')
                                ->paginate(10);
        return Response()->json($ordenes, 200);
    }

    public function filter(Request $request)
    {

            $ordenes = Cotizacion::when($this->esUsuarioRolVentasCotizaciones(), function ($query) {
                                    $query->where('id_usuario', Auth::id());
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                                })
                                ->when($request->sucursal_id, function($query) use ($request){
                                    return $query->where('sucursal_id', $request->sucursal_id);
                                })
                                ->when($request->tipo_servicio, function($query) use ($request){
                                    return $query->where('tipo_servicio', $request->tipo_servicio);
                                })
                                ->when($request->usuario_id, function($query) use ($request){
                                    return $query->where('usuario_id', $request->usuario_id);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->orderBy('id','asc')->paginate(100000);

        return Response()->json($ordenes, 200);
    }

    public function store(StoreCotizacionRequest $request)
    {


        if ($request->cotizacion_id != null) {
            if ($request->id)
                $cotizacion = CotizacionVenta::findOrFail($request->id);
            else
                $cotizacion = new CotizacionVenta;
            $cotizacion->fill($request->all());
            $cotizacion->save();
            return Response()->json($cotizacion, 200);
        }

        if ($this->esUsuarioRolVentasCotizaciones()) {
            $request->merge(['id_usuario' => Auth::id()]);
        }

        if ($request->id){
            $orden = Cotizacion::findOrFail($request->id);
            if ($this->esUsuarioRolVentasCotizaciones() && (int) $orden->id_usuario !== (int) Auth::id()) {
                abort(403, 'No autorizado');
            }
            if ($this->aplicarRestriccionesCotizacionesVendedores() && $request->has('estado')
                && (string) $request->estado !== (string) $orden->estado) {
                abort(403, 'No autorizado a modificar el estado de la cotización');
            }
        }else{
            $orden = new Cotizacion;
        }

        // Excluir id_canal al guardar cotizaciones ya que no aplica
        $data = $request->all();
        unset($data['id_canal']);
        $orden->fill($data);
        $orden->save();

        return Response()->json($orden, 200);
    }

    public function facturacion(FacturacionCotizacionRequest $request)
    {

        // Guardamos el cliente
        if (isset($request->cliente['id']) || isset($request->cliente['nombre'])) {
            if (isset($request->cliente['id']))
                $cliente = Cliente::findOrFail($request->cliente['id']);
            else
                $cliente = new Cliente;

            $cliente->fill($request->cliente);
            $cliente->save();
            $request['cliente_id'] = $cliente->id;
        }

        // Guardamos la orden
        if ($request->id)
            $orden = Cotizacion::findOrFail($request->id);
        else
            $orden = new Cotizacion;

        // Excluir id_canal al guardar cotizaciones ya que no aplica
        $data = $request->all();
        unset($data['id_canal']);
        $orden->fill($data);
        $orden->save();


        // Guardamos los detalles

        foreach ($request->detalles as $det) {
            if (isset($det['id']))
                $detalle = Detalle::findOrFail($det['id']);
            else
                $detalle = new Detalle;

            $det['orden_id'] = $orden->id;

            $detalle->fill($det);
            $detalle->save();
        }


        return Response()->json($orden, 200);
    }


    public function delete($id)
    {
        $orden = Cotizacion::findOrFail($id);
        if ($this->esUsuarioRolVentasCotizaciones() && (int) $orden->id_usuario !== (int) Auth::id()) {
            abort(403, 'No autorizado');
        }
        foreach ($orden->detalles as $detalle) {
            $detalle->delete();
        }
        $orden->delete();

        return Response()->json($orden, 201);
    }

    // public function generarDoc($id)
    // {

    //     $venta = Cotizacion::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

    //     $pdf = PDF::loadView('reportes.facturacion.cotizacion', compact('venta'));
    //     $pdf->setPaper('US Letter', 'portrait');
    //     return $pdf->stream('cotizacion-' . $venta->id . '.pdf');
    // }

    public function generarDoc($id, $tipo = null)
    {
        if ($tipo === 'cotizacion') {
            Log::info('Generando documento de cotización');
            $venta = CotizacionVenta::where('id', $id)
                ->with('detalles.producto', 'detalles.customFields.customFieldValue', 'detalles.customFields.customField', 'cliente', 'empresa.currency',)
                ->firstOrFail();

            if ($this->esUsuarioRolVentasCotizaciones() && (int) $venta->id_usuario !== (int) Auth::id()) {
                abort(403, 'No autorizado');
            }

            $pdfData = array_merge(compact('venta'), $this->cotizacionPdfViewData($venta));

            if(Auth::user()->id_empresa == 420){ //420
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.cotizacion-inversiones-andre', compact('venta'));
                $pdf->setPaper('US Letter', 'portrait');
            }elseif(Auth::user()->id_empresa == 498){ //13
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.cotizacion-grupo-split', compact('venta'));
                $pdf->setPaper('US Letter', 'portrait');
            }elseif(Auth::user()->id_empresa == 2){ //2 Super Admin
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.cotizacion-smartpyme', compact('venta'));
                $pdf->setPaper('US Letter', 'portrait');
            }else{
                $pdf = PDF::loadView('reportes.facturacion.cotizacion', compact('venta'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            return $pdf->stream('cotizacion-' . $venta->id . '.pdf');
        } else {
            Log::info('Generando documento de factura');
            $venta = Cotizacion::where('id', $id)
                ->with('detalles', 'cliente')
                ->firstOrFail();

            $pdf = PDF::loadView('reportes.facturacion.factura', compact('venta'));
        }

        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream($tipo == 'cotizacion' ? 'cotizacion-' : 'factura-' . $venta->id . '.pdf');
    }

    public function vendedor()
    {

        $ordenes = Cotizacion::orderBy('id', 'desc')->where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)->paginate(10);

        return Response()->json($ordenes, 200);
    }

    public function vendedorBuscador($txt)
    {

        $ordenes = Cotizacion::where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)
            ->with('cliente', function ($q) use ($txt) {
                $q->where('nombre', 'like', '%' . $txt . '%');
            })
            ->orwhere('estado', 'like', '%' . $txt . '%')
            ->paginate(10);
        return Response()->json($ordenes, 200);
    }

    public function export(Request $request)
    {
        //dd($request->all());
        $cotizaciones = new CotizacionesExport();
        $cotizaciones->filter($request);

        return Excel::download($cotizaciones, 'cotizaciones.xlsx');
    }

    public function changeStateCotizacion(Request $request)
    {
        $cotizacion = CotizacionVenta::findOrFail($request->id);
        $cotizacion->estado = $request->estado;
        $cotizacion->save();
        return Response()->json($cotizacion, 200);
    }

    public function duplicarCotizacion(Request $request)
    {
        $cotizacion = CotizacionVenta::findOrFail($request->id);
        $nuevaCotizacion = $cotizacion->replicate();
        $nuevaCotizacion->save();

        return response()->json($nuevaCotizacion, 200);
    }
}
