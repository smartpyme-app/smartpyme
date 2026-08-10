<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaisConfiguracion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaisConfiguracionController extends Controller
{
    public function index(Request $request)
    {
        $q = PaisConfiguracion::query()->orderBy('pais')->orderBy('modulo');

        if ($request->filled('pais')) {
            $q->pais($request->pais);
        }
        if ($request->filled('modulo')) {
            $q->modulo($request->modulo);
        }

        return response()->json($q->get(), 200);
    }

    public function read($id)
    {
        return response()->json(PaisConfiguracion::findOrFail($id), 200);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $row = PaisConfiguracion::create($data);

        return response()->json($row, 200);
    }

    public function update(Request $request, $id)
    {
        $row = PaisConfiguracion::findOrFail($id);
        $data = $this->validated($request, $row->id);

        $row->update($data);

        return response()->json($row->fresh(), 200);
    }

    public function delete($id)
    {
        PaisConfiguracion::findOrFail($id)->delete();

        return response()->json(['ok' => true], 200);
    }

    /** @return array{pais: string, modulo: string, configuracion: array} */
    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'pais' => 'required|string|max:3',
            'modulo' => 'required|string|max:50',
            'configuracion' => 'required|array',
        ]);

        $data['pais'] = strtoupper($data['pais']);
        $data['modulo'] = strtolower(trim($data['modulo']));

        $exists = PaisConfiguracion::query()
            ->where('pais', $data['pais'])
            ->where('modulo', $data['modulo'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'modulo' => ['Ya existe configuración para ese país y módulo.'],
            ]);
        }

        return $data;
    }
}
