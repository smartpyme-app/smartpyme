<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use Illuminate\Http\Request;
use App\Models\OrdenPago as Pago;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PagosController extends Controller
{
    public function index(Request $request)
    {

        $pagos = Pago::latest()->paginate();

        return Response()->json($pagos, 200);
    }

    public function read($id)
    {

        $pago = Pago::where('id', $id)->firstOrFail();
        return Response()->json($pago, 200);
    }

    public function newPayment(Request $request)
    {
        // Validación de datos
        $validator = Validator::make($request->all(), [
            'empresa_id' => 'required|exists:empresas,id',
            'plan_id' => 'required|exists:planes,id',
            'metodo_pago' => 'required|string',
            'monto' => 'required|numeric|min:0',
            'estado' => 'required|in:Completado,Rechazado,Pendiente',
            'fecha_transaccion' => 'required|date',
            'fecha_proximo_pago' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Obtener el usuario asociado a la empresa
        $empresa = Empresa::findOrFail($request->empresa_id);
        $usuario = User::where('id_empresa', $empresa->id)->first();

        if (!$usuario) {
            return response()->json(['error' => 'No se encontró un usuario asociado a esta empresa'], 422);
        }

        // Crear orden de pago
        $ordenPago = new OrdenPago();
        $ordenPago->id_orden = 'ORD-M-' . time() . '-' . Str::random(8);
        $ordenPago->id_usuario = $usuario->id;
        $ordenPago->id_plan = $request->plan_id;
        $ordenPago->nombre_cliente = $usuario->name;
        $ordenPago->email_cliente = $usuario->email;
        $ordenPago->telefono_cliente = $usuario->telefono;
        $ordenPago->plan = Plan::find($request->plan_id)->nombre;
        $ordenPago->monto = $request->monto;
        $ordenPago->estado = $request->estado;
        $ordenPago->fecha_transaccion = $request->fecha_transaccion;
        $ordenPago->tipo_pago = config('constants.TIPO_PAGO_MANUAL');
        $ordenPago->save();

        // Si el estado es completado, actualizar o crear la suscripción
        if ($request->estado === config('constants.ESTADO_ORDEN_COMPLETADO')) {
            $plan = Plan::find($request->plan_id);
            $tipoPlan = $plan->duracion_dias == 30 ? 'Mensual' : 'Anual';

            // Actualizar o crear suscripción
            Suscripcion::updateOrCreate(
                ['usuario_id' => $usuario->id],
                [
                    'empresa_id' => $empresa->id,
                    'plan_id' => $plan->id,
                    'tipo_plan' => $tipoPlan,
                    'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                    'monto' => $request->monto,
                    'metodo_pago' => $request->metodo_pago,
                    'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_COMPLETADO'),
                    'fecha_ultimo_pago' => $request->fecha_transaccion,
                    'fecha_proximo_pago' => $request->fecha_proximo_pago
                ]
            );

            // Actualizar empresa
            $empresa->update([
                'metodo_pago' => $request->metodo_pago
            ]);
        }

        return response()->json($ordenPago, 201);
    }

    public function updatePayment(Request $request, $id)
    {
        $ordenPago = OrdenPago::findOrFail($id);
        
        // Validación de datos
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:planes,id',
            'metodo_pago' => 'required|string',
            'monto' => 'required|numeric|min:0',
            'estado' => 'required|in:Completado,Rechazado,Pendiente',
            'fecha_transaccion' => 'required|date',
            'fecha_proximo_pago' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Actualizar orden de pago
        $ordenPago->id_plan = $request->plan_id;
        $ordenPago->plan = Plan::find($request->plan_id)->nombre;
        $ordenPago->monto = $request->monto;
        $ordenPago->estado = $request->estado;
        $ordenPago->fecha_transaccion = $request->fecha_transaccion;
        $ordenPago->save();

        // Si el estado ha cambiado a completado, actualizar o crear la suscripción
        if ($request->estado === config('constants.ESTADO_ORDEN_COMPLETADO') && $ordenPago->getOriginal('estado') !== config('constants.ESTADO_ORDEN_COMPLETADO')) {
            $plan = Plan::find($request->plan_id);
            $usuario = User::find($ordenPago->id_usuario);
            $empresa = Empresa::find($usuario->id_empresa);
            $tipoPlan = $plan->duracion_dias == 30 ? 'Mensual' : 'Anual';
            
            // Actualizar o crear suscripción
            Suscripcion::updateOrCreate(
                ['usuario_id' => $usuario->id],
                [
                    'empresa_id' => $empresa->id,
                    'plan_id' => $plan->id,
                    'tipo_plan' => $tipoPlan,
                    'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                    'monto' => $request->monto,
                    'metodo_pago' => $request->metodo_pago,
                    'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_COMPLETADO'),
                    'fecha_ultimo_pago' => $request->fecha_transaccion,
                    'fecha_proximo_pago' => $request->fecha_proximo_pago
                ]
            );
            
            // Actualizar empresa
            $empresa->update([
                'metodo_pago' => $request->metodo_pago
            ]);
        }

        return response()->json($ordenPago);
    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'          => 'required|max:255',
            'precio'          => 'required',
            'id_producto'     => 'required',
        ]);

        if($request->id)
            $pago = Pago::findOrFail($request->id);
        else
            $pago = new Pago;

        $pago->fill($request->all());
        $pago->save();

        return Response()->json($pago, 200);

    }


    public function generarVenta($id)
    {
        $pago = Pago::where('id', $id)->firstOrFail();
        $venta = $pago->generarVenta();

        return Response()->json($venta, 200);

    }

    public function delete($id)
    {
       
        $pago = Pago::findOrFail($id);
        $pago->delete();

        return Response()->json($pago, 201);

    }
}
