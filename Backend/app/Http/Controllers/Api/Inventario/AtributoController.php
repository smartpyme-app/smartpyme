<?php

namespace App\Http\Controllers\Api\Inventario;

use Illuminate\Http\Request;
use App\Models\Inventario\Atributo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AtributoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $atributos = Atributo::get();
        return response()->json($atributos, 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                'tipo' => 'required|max:255',
                'valor' => 'required|max:255',
            ],
            [
                'tipo.required' => 'El tipo es obligatorio.',
                'tipo.max' => 'El tipo no debe superar los 255 caracteres.',
                'valor.required' => 'El valor es obligatorio.',
                'valor.max' => 'El valor no debe superar los 255 caracteres.',
            ]
        );

        try {
            //DB
            DB::beginTransaction();

            $user = Auth::user();
            $request->merge(['id_empresa' => $user->id_empresa]);

            $atributo = new Atributo();
            $atributo->fill($request->all());
            $atributo->save();
            DB::commit();
            return response()->json($atributo, 200);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
        }
    }



    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
    
    }


}
