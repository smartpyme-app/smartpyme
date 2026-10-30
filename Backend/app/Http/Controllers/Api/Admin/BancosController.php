<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Bancos\StoreOrDeleteBancoRequest;
use App\Models\Admin\Banco;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BancosController extends Controller
{
    public function index()
    {
        return response()->json($this->catalogWithActivoFlag(), 200);
    }

    public function list()
    {
        $persisted = Banco::orderBy('nombre')->get();
        if ($persisted->isNotEmpty()) {
            return response()->json($persisted, 200);
        }

        // ponytail: sin bancos activados en BD, exponer catálogo del país para selects (abonos, ventas).
        $fallback = $this->catalogWithActivoFlag()->map(fn (array $b) => ['nombre' => $b['nombre']])->values();

        return response()->json($fallback, 200);
    }

    public function storeOrDelete(StoreOrDeleteBancoRequest $request)
    {
        if (Banco::where('nombre', $request->nombre)->first()) {
            $banco = Banco::where('nombre', $request->nombre)->first();
            $banco->delete();

            return response()->json($banco, 201);
        }

        $banco = new Banco;
        $banco->fill($request->all());
        $banco->save();

        return response()->json($banco, 200);
    }

    /** @return Collection<int, array{nombre: string, activo: bool}> */
    private function catalogWithActivoFlag(): Collection
    {
        $listaDeBancos = Banco::get();
        $nombres = $this->catalogNamesForEmpresa();

        return collect($nombres)->map(function (string $nombre) use ($listaDeBancos) {
            return [
                'nombre' => $nombre,
                'activo' => $listaDeBancos->where('nombre', $nombre)->isNotEmpty(),
            ];
        });
    }

    /** @return list<string> */
    private function catalogNamesForEmpresa(): array
    {
        $pais = Auth::user()?->empresa()?->pluck('pais')->first() ?? '';

        if ($pais === 'Costa Rica') {
            return [
                'Banco Nacional de Costa Rica',
                'Banco de Costa Rica',
                'BAC Credomatic',
                'Banco Popular',
                'Scotiabank',
                'Banco Promerica',
                'Banco Davivienda',
                'Banco Lafise',
                'Banco Cathay',
                'Coopenae',
            ];
        }

        $bancos = [
            'Banco Agrícola',
            'Banco Azul',
            'BAC Credomatic',
            'Banco Cuscatlán',
            'Banco Promerica',
            'Banco Davivienda',
            'Banco Fedecrédito',
            'Banco Atlántida',
            'Banco Industrial',
            'Banrural',
            'Banco Hipotecario',
        ];

        if ($pais === 'Honduras') {
            $bancos[] = 'Ficohsa';
        }

        return $bancos;
    }
}
