<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use App\Models\Admin\Empresa;
use App\Models\Admin\FormaDePago;
use App\Models\Wompi;

class FormasDePagosController extends Controller
{
    

    public function index() {
       
        $formasDePago = FormaDePago::get();        
        $formas = collect();

        $formas->push(['nombre' => 'Efectivo', 'activo' => $formasDePago->where('nombre', 'Efectivo')->first() ? true : false ]);
        $formas->push(['nombre' => 'Transferencia', 'activo' => $formasDePago->where('nombre', 'Transferencia')->first() ? true : false ]);
        $formas->push(['nombre' => 'Tarjeta de crédito/débito', 'activo' => $formasDePago->where('nombre', 'Tarjeta de crédito/débito')->first() ? true : false ]);
        $formas->push(['nombre' => 'Cheque', 'activo' => $formasDePago->where('nombre', 'Cheque')->first() ? true : false ]);
        $formas->push(['nombre' => 'Wompi', 'activo' => $formasDePago->where('nombre', 'Wompi')->first() ? true : false ]);
        $formas->push(['nombre' => 'Paypal', 'activo' => $formasDePago->where('nombre', 'Paypal')->first() ? true : false ]);
        $formas->push(['nombre' => 'Bitcoin', 'activo' => $formasDePago->where('nombre', 'Bitcoin')->first() ? true : false ]);
        $formas->push(['nombre' => 'Compra click', 'activo' => $formasDePago->where('nombre', 'Compra click')->first() ? true : false ]);
        $formas->push(['nombre' => 'N1co', 'activo' => $formasDePago->where('nombre', 'N1co')->first() ? true : false ]);
        $formas->push(['nombre' => 'Otro', 'activo' => $formasDePago->where('nombre', 'Otro')->first() ? true : false ]);
        
        return Response()->json($formas, 200);

    }

    public function list() {
       
        $formasDePago = FormaDePago::get();        
        
        return Response()->json($formasDePago, 200);

    }

    public function storeOrDelete(Request $request)
    {

        if (FormaDePago::where('nombre', $request->nombre)->first()) {
            $formasDePago = FormaDePago::where('nombre', $request->nombre)->first();
            $formasDePago->delete();
            return Response()->json($formasDePago, 201);
        }
        
        $this->validate($request, [
            'nombre'        => 'required|string|max:150',
            'orden'         => 'numeric|nullable',
            'id_empresa'    => 'required|numeric',
        ]);
        
        $formasDePago = new FormaDePago();
        $formasDePago->fill($request->all());
        $formasDePago->save();

        return Response()->json($formasDePago, 200);

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

        return Response()->json(['error' => 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para sus ventas.', 'code' => 400], 400);

    }

}
