<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Admin\Empresa;
use App\Models\Admin\FormaDePago;
use App\Models\Wompi;

class FormasDePagosController extends Controller
{
    protected function getEmpresa()
    {
        $user = Auth::user();
        if (!$user || !$user->id_empresa) {
            return null;
        }
        return Empresa::find($user->id_empresa);
    }

    protected function isModuloBancos(): bool
    {
        $empresa = $this->getEmpresa();
        return $empresa ? $empresa->isModuloBancos() : false;
    }

    public function index()
    {
        if ($this->isModuloBancos()) {
            return response()->json(FormaDePago::with('banco')->get(), 200);
        }

        $formasDePago = FormaDePago::get();
        $formas = collect();

        $formas->push(['nombre' => 'Efectivo', 'activo' => $formasDePago->where('nombre', 'Efectivo')->first() ? true : false]);
        $formas->push(['nombre' => 'Transferencia', 'activo' => $formasDePago->where('nombre', 'Transferencia')->first() ? true : false]);
        $formas->push(['nombre' => 'Tarjeta de crédito/débito', 'activo' => $formasDePago->where('nombre', 'Tarjeta de crédito/débito')->first() ? true : false]);
        $formas->push(['nombre' => 'Cheque', 'activo' => $formasDePago->where('nombre', 'Cheque')->first() ? true : false]);
        $formas->push(['nombre' => 'Contra entrega', 'activo' => $formasDePago->where('nombre', 'Contra entrega')->first() ? true : false]);
        $formas->push(['nombre' => 'Wompi', 'activo' => $formasDePago->where('nombre', 'Wompi')->first() ? true : false]);
        $formas->push(['nombre' => 'Paypal', 'activo' => $formasDePago->where('nombre', 'Paypal')->first() ? true : false]);
        $formas->push(['nombre' => 'Bitcoin', 'activo' => $formasDePago->where('nombre', 'Bitcoin')->first() ? true : false]);
        $formas->push(['nombre' => 'Compra click', 'activo' => $formasDePago->where('nombre', 'Compra click')->first() ? true : false]);
        $formas->push(['nombre' => 'N1co', 'activo' => $formasDePago->where('nombre', 'N1co')->first() ? true : false]);
        $formas->push(['nombre' => 'Otro', 'activo' => $formasDePago->where('nombre', 'Otro')->first() ? true : false]);

        return response()->json($formas, 200);
    }

    public function list()
    {
        $formasDePago = FormaDePago::with('banco')->get();

        return response()->json($formasDePago, 200);
    }

    public function storeOrDelete(Request $request)
    {
        if ($this->isModuloBancos()) {
            return $this->store($request);
        }

        if (FormaDePago::where('nombre', $request->nombre)->first()) {
            $formasDePago = FormaDePago::where('nombre', $request->nombre)->first();
            $formasDePago->delete();
            return response()->json($formasDePago, 201);
        }

        $this->validate($request, [
            'nombre' => 'required|string|max:150',
            'orden' => 'numeric|nullable',
            'id_empresa' => 'required|numeric',
        ]);

        $formasDePago = new FormaDePago();
        $formasDePago->fill($request->all());
        $formasDePago->save();

        return response()->json($formasDePago, 200);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'nombre' => 'required|string|max:150',
            'orden' => 'numeric|nullable',
            'id_empresa' => 'required|numeric',
        ]);

        if ($request->id) {
            $formasDePago = FormaDePago::findOrFail($request->id);
        } else {
            $formasDePago = new FormaDePago();
        }

        $data = $request->all();
        if (isset($data['id_banco']) && $data['id_banco'] === '') {
            $data['id_banco'] = null;
        }
        $formasDePago->fill($data);
        $formasDePago->save();

        return response()->json($formasDePago->load('banco'), 200);
    }

    public function delete($id)
    {
        $formasDePago = FormaDePago::findOrFail($id);
        $formasDePago->delete();

        return response()->json($formasDePago, 201);
    }


    public function wompi(Request $request){

        $this->validate($request, [
            'wompi_id'              => 'required|string|max:255',
            'wompi_aplicativo'      => 'required|string|max:255',
            'wompi_secret'          => 'required|string|max:255',
        ]);
        
        $empresa = Empresa::findOrfail($request->id);
        $empresa->fill($request->all());
        $empresa->save();

        $wompi = new Wompi($empresa);
        $autenticate = $wompi->autenticate();

        if (isset($autenticate['error'])) {
            return Response()->json(['error' => 'No se pudo realizar la conexión con Wompi, verifique los datos.', 'code' => 500], 500);
        }

        return Response()->json(['message' => 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para sus ventas.', 'code' => 200], 200);

    }

}
