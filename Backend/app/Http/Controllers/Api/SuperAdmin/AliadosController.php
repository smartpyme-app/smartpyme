<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AliadosController extends Controller
{
    public function index(Request $request)
    {
        $query = Aliado::query();

        if ($request->filled('buscador')) {
            $buscador = $request->buscador;
            $query->where(function ($q) use ($buscador) {
                $q->where('nombre', 'like', "%{$buscador}%")
                    ->orWhere('descripcion', 'like', "%{$buscador}%");
            });
        }

        if ($request->estado !== null && $request->estado !== '') {
            $query->where('activo', $request->estado == '1');
        }

        if ($request->boolean('list')) {
            return response()->json($query->orderBy('nombre')->get(['id', 'nombre']), 200);
        }

        $ordenes = ['nombre', 'activo', 'created_at'];
        $orden = in_array($request->get('orden'), $ordenes, true) ? $request->get('orden') : 'nombre';
        $direccion = $request->get('direccion') === 'desc' ? 'desc' : 'asc';
        $paginate = min(max((int) $request->get('paginate', 10), 1), 100);

        return response()->json(
            $query->orderBy($orden, $direccion)->paginate($paginate),
            200
        );
    }

    public function store(Request $request)
    {
        $request->merge([
            'nombre' => trim((string) $request->nombre),
        ]);

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:aliados,id'],
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('aliados', 'nombre')->ignore($request->id),
            ],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ], [
            'nombre.required' => 'El nombre es requerido.',
            'nombre.unique' => 'Ya existe un aliado con ese nombre.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
        ]);

        $aliado = !empty($data['id'])
            ? Aliado::findOrFail($data['id'])
            : new Aliado;

        $descripcion = $data['descripcion'] ?? null;
        $aliado->fill([
            'nombre' => $data['nombre'],
            'descripcion' => $descripcion === '' ? null : $descripcion,
            'activo' => array_key_exists('activo', $data) ? $data['activo'] : true,
        ]);
        $aliado->save();

        return response()->json($aliado, 200);
    }

    public function delete($id)
    {
        $aliado = Aliado::findOrFail($id);
        $aliado->delete();

        return response()->json($aliado, 200);
    }
}
