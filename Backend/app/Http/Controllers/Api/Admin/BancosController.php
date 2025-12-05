<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use App\Models\Admin\Banco;
use App\Http\Requests\Admin\Bancos\StoreOrDeleteBancoRequest;

class BancosController extends Controller
{


    public function index() {

        $listaDeBancos = Banco::get();

        $bancos = collect();

        $bancos->push(['nombre' => 'Banco Agrícola', 'activo' => $listaDeBancos->where('nombre', 'Banco Agrícola')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Azul', 'activo' => $listaDeBancos->where('nombre', 'Banco Azul')->first() ? true : false ]);
        $bancos->push(['nombre' => 'BAC Credomatic', 'activo' => $listaDeBancos->where('nombre', 'BAC Credomatic')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Cuscatlán', 'activo' => $listaDeBancos->where('nombre', 'Banco Cuscatlán')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Promerica', 'activo' => $listaDeBancos->where('nombre', 'Banco Promerica')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Davivienda', 'activo' => $listaDeBancos->where('nombre', 'Banco Davivienda')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Fedecrédito', 'activo' => $listaDeBancos->where('nombre', 'Banco Fedecrédito')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Atlántida', 'activo' => $listaDeBancos->where('nombre', 'Banco Atlántida')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Industrial', 'activo' => $listaDeBancos->where('nombre', 'Banco Industrial')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banrural', 'activo' => $listaDeBancos->where('nombre', 'Banrural')->first() ? true : false ]);
        $bancos->push(['nombre' => 'Banco Hipotecario', 'activo' => $listaDeBancos->where('nombre', 'Banco Hipotecario')->first() ? true : false ]);

        return Response()->json($bancos, 200);

    }

    public function list() {

        $bancos = Banco::get();

        return Response()->json($bancos, 200);

    }

    public function storeOrDelete(StoreOrDeleteBancoRequest $request)
    {

        if (Banco::where('nombre', $request->nombre)->first()) {
            $banco = Banco::where('nombre', $request->nombre)->first();
            $banco->delete();
            return Response()->json($banco, 201);
        }


        $banco = new Banco();

        $banco->fill($request->all());
        $banco->save();

        return Response()->json($banco, 200);

    }

}
