<?php

namespace App\Http\Controllers;

use App\Models\ComboProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComboProductoController extends Controller
{

    public function index(Request $request)
    {
        $combos = ComboProducto::when($request->buscador, function ($query) use ($request) {
            return $query->where('nombre', 'like', '%' . $request->buscador . '%')
                ->orwhere('codigo', 'like', "%" . $request->buscador . "%")
                ->orwhere('barcode', 'like', "%" . $request->buscador . "%")
                ->orwhere('etiquetas', 'like', "%" . $request->buscador . "%")
                ->orwhere('marca', 'like', "%" . $request->buscador . "%")
                ->orwhere('descripcion', 'like', "%" . $request->buscador . "%");
        })
            // ->when($request->sin_stock, function($query) use ($request){
            //     return $query->join('inventario', 'productos.id', '=', 'inventario.id_producto')
            //     ->whereRaw('COALESCE(inventario.stock, 0) < COALESCE(inventario.stock_minimo, 0)');
            // })
            ->when($request->nombre, function ($q) use ($request) {
                $q->where('nombre', $request->nombre);
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->paginate($request->paginate);
        return response()->json($combos, 200);
    }
    public function store(Request $request)
    {
        $request->validate([
            "codigo_combo" => "required",
            "descripcion" => "required",
            'nombre' => 'required',
            'detalles' => 'required|array',
            "precio" => "required",
            "precio_total" => "required",
        ]);

        if (ComboProducto::where('codigo_combo', $request->codigo_combo)->exists()) {
            return response()->json(["error" => "Un combo con codigo $request->codigo_combo ya fue registrado anteriormente"], 400);
        }

        DB::beginTransaction();

        $empresa_id = auth()->user()->id_empresa;

        $detalles = collect($request->detalles);
        $newcombo = ComboProducto::create([
            'codigo_combo' => $request->codigo_combo,
            'descripcion' => $request->descripcion,
            'nombre' => $request->nombre,
            "id_empresa" => $empresa_id,
            "precio_total" => $request->precio_final,
            "precio" => $request->precio,
            "costo_total" => $detalles->sum('costo'),
        ]);

        $newcombo
            ->detalles()
            ->createMany(
                $detalles->map(fn($detalle) => [
                    "id_producto" => $detalle["id_producto"],
                    "id_combo" => $newcombo->id,
                    "cantidad" => $detalle["cantidad"],
                    "precio" => $detalle["precio"],
                    "costo" => $detalle["costo"],
                ])->toArray()
            );

        DB::commit();

        return response()->json(["message" => "Combo creado con éxito", "combo" => $newcombo->load("detalles")], 201);
    }

    public function update(Request $request)
    {
        $request->validate([
            "id" => "required",
            "codigo_combo" => "required",
            "descripcion" => "required",
            'nombre' => 'required',
            'detalles' => 'required|array',
        ]);

        $combo = ComboProducto::find($request->id);

        if (!$combo) {
            return response()->json(["error" => "Combo no encontrado"], 404);
        }

        DB::beginTransaction();

        $detalles = collect($request->detalles);
        $combo->update([
            'codigo_combo' => $request->codigo_combo,
            'descripcion' => $request->descripcion,
            'nombre' => $request->nombre,
            "precio_total" => $request->precio_final,
            "costo_total" => $detalles->sum('costo'),
        ]);

        $combo->detalles()->delete();

        $combo
            ->detalles()
            ->createMany(
                $detalles->map(fn($detalle) => [
                    "id_producto" => $detalle["id_producto"],
                    "id_combo" => $combo->id,
                    "cantidad" => $detalle["cantidad"],
                    "precio" => $detalle["precio"],
                    "costo" => $detalle["costo"],
                ])->toArray()
            );

        DB::commit();

        return response()->json(["message" => "Combo actualizado con éxito", "combo" => $combo->load("detalles")], 200);
    }

    public function show(int $id)
    {
        $combo = ComboProducto::with("detalles.producto")->where("id", $id)->first();
        return response()->json($combo, 200);
    }

    public function changeState(Request $request)
    {
        $request->validate([
            "id" => "required",
            "estado" => "required",
        ]);

        $combo = ComboProducto::find($request->id);

        if (!$combo) {
            return response()->json(["error" => "Combo no encontrado"], 404);
        }

        $combo->update([
            "estado" => $request->estado,
        ]);

        return response()->json(["message" => "Estado actualizado con éxito", "combo" => $combo], 200);
    }
}
