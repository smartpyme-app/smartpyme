<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\Admin\IdentificacionDefaultPorPais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PaisConfiguracionController extends Controller
{
    /** Catálogo de tipos de identificación (pais_configuracion / plantilla). Auth de empresa, no solo super admin. */
    public function identificacionOpciones(Request $request)
    {
        $empresa = Auth::user()->empresa ?? Empresa::find(Auth::user()->id_empresa);
        $pais = $request->query('pais')
            ?: FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);
        $pais = strtoupper((string) $pais);
        $cfg = IdentificacionDefaultPorPais::configuracion($pais);

        return response()->json([
            'pais' => $pais,
            'default_persona' => $cfg['default_persona'],
            'default_empresa' => $cfg['default_empresa'],
            'default_extranjero' => $cfg['default_extranjero'],
            'tipos' => IdentificacionDefaultPorPais::tiposReceptor($pais),
            'tipos_emisor' => IdentificacionDefaultPorPais::tiposEmisor($pais),
        ], 200);
    }

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
