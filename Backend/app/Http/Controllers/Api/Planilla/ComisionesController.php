<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Models\Planilla\ComisionEmpleado;
use App\Models\User;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;

class ComisionesController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $query = ComisionEmpleado::with(['vendedor'])
            ->porEmpresa($idEmpresa)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        $this->aplicarFiltros($query, $request);

        $paginate = $request->get('paginate', 15);

        return $query->paginate($paginate);
    }

    public function summary(Request $request)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $query = ComisionEmpleado::porEmpresa($idEmpresa);
        $this->aplicarFiltros($query, $request);

        $totales = (clone $query)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_comision), 0) as total_comisiones')
            ->first();

        $agrupado = (clone $query)
            ->selectRaw('id_vendedor, COUNT(*) as cantidad, COALESCE(SUM(monto_comision), 0) as total_comisiones')
            ->groupBy('id_vendedor')
            ->get();

        $vendedores = User::whereIn('id', $agrupado->pluck('id_vendedor')->filter())
            ->get()
            ->keyBy('id');

        $porVendedor = $agrupado->map(function ($row) use ($vendedores) {
            return [
                'id_vendedor' => (int) $row->id_vendedor,
                'cantidad' => (int) $row->cantidad,
                'total_comisiones' => (float) $row->total_comisiones,
                'vendedor' => $vendedores->get($row->id_vendedor),
            ];
        })->values();

        return response()->json([
            'cantidad' => (int) ($totales->cantidad ?? 0),
            'total_comisiones' => (float) ($totales->total_comisiones ?? 0),
            'por_vendedor' => $porVendedor,
        ]);
    }

    public function show($id)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $comision = ComisionEmpleado::with(['vendedor', 'venta'])
            ->porEmpresa($idEmpresa)
            ->findOrFail($id);

        return response()->json($comision);
    }

    public function store(Request $request)
    {
        $idEmpresa = auth()->user()->id_empresa;
        $data = $this->validar($request);
        $vendedor = $this->vendedorDeEmpresa((int) $data['id_vendedor'], $idEmpresa);

        [$idVenta, $ventaEncontrada] = $this->resolverVenta(
            $idEmpresa,
            $data['correlativo_referencia'] ?? null
        );

        $monto = ComisionEmpleado::calcularMonto(
            (float) $data['base_calculo'],
            (float) $data['tasa_comision']
        );

        $comision = ComisionEmpleado::create([
            'id_vendedor' => $vendedor->id,
            'id_empresa' => $idEmpresa,
            'origen' => $data['origen'],
            'correlativo_referencia' => $data['correlativo_referencia'] ?? null,
            'id_venta' => $idVenta,
            'categoria' => $data['categoria'] ?? null,
            'base_calculo' => $data['base_calculo'],
            'tasa_comision' => $data['tasa_comision'],
            'monto_comision' => $monto,
            'fecha' => $data['fecha'],
            'notas' => $data['notas'] ?? null,
        ]);

        $comision->load('vendedor');

        return response()->json([
            'comision' => $comision,
            'venta_encontrada' => $ventaEncontrada,
            'advertencia' => $ventaEncontrada || empty($data['correlativo_referencia'])
                ? null
                : 'No se encontró una venta con ese correlativo. El registro se guardó igual.',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $idEmpresa = auth()->user()->id_empresa;
        $comision = ComisionEmpleado::porEmpresa($idEmpresa)->findOrFail($id);

        $data = $this->validar($request);
        $vendedor = $this->vendedorDeEmpresa((int) $data['id_vendedor'], $idEmpresa);

        [$idVenta, $ventaEncontrada] = $this->resolverVenta(
            $idEmpresa,
            $data['correlativo_referencia'] ?? null
        );

        $monto = ComisionEmpleado::calcularMonto(
            (float) $data['base_calculo'],
            (float) $data['tasa_comision']
        );

        $comision->update([
            'id_vendedor' => $vendedor->id,
            'origen' => $data['origen'],
            'correlativo_referencia' => $data['correlativo_referencia'] ?? null,
            'id_venta' => $idVenta,
            'categoria' => $data['categoria'] ?? null,
            'base_calculo' => $data['base_calculo'],
            'tasa_comision' => $data['tasa_comision'],
            'monto_comision' => $monto,
            'fecha' => $data['fecha'],
            'notas' => $data['notas'] ?? null,
        ]);

        $comision->load('vendedor');

        return response()->json([
            'comision' => $comision,
            'venta_encontrada' => $ventaEncontrada,
            'advertencia' => $ventaEncontrada || empty($data['correlativo_referencia'])
                ? null
                : 'No se encontró una venta con ese correlativo. El registro se guardó igual.',
        ]);
    }

    public function destroy($id)
    {
        $idEmpresa = auth()->user()->id_empresa;
        $comision = ComisionEmpleado::porEmpresa($idEmpresa)->findOrFail($id);
        $comision->delete();

        return response()->json($comision);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'id_vendedor' => 'required|integer|exists:users,id',
            'origen' => 'required|string|in:' . implode(',', ComisionEmpleado::ORIGENES),
            'correlativo_referencia' => 'nullable|string|max:100',
            'categoria' => 'nullable|string|max:255',
            'base_calculo' => 'required|numeric|min:0.01',
            'tasa_comision' => 'required|numeric|min:0|max:100',
            'fecha' => 'required|date',
            'notas' => 'nullable|string|max:2000',
        ]);
    }

    private function vendedorDeEmpresa(int $idVendedor, int $idEmpresa): User
    {
        return User::where('id', $idVendedor)
            ->where('id_empresa', $idEmpresa)
            ->firstOrFail();
    }

    /**
     * Soft-check: busca venta por correlativo en la empresa.
     * Si no hay correlativo o no existe venta, no bloquea el guardado.
     *
     * @return array{0: int|null, 1: bool}
     */
    private function resolverVenta(int $idEmpresa, ?string $correlativo): array
    {
        if ($correlativo === null || trim($correlativo) === '') {
            return [null, false];
        }

        $venta = Venta::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('correlativo', trim($correlativo))
            ->first();

        if (!$venta) {
            return [null, false];
        }

        return [(int) $venta->id, true];
    }

    private function aplicarFiltros($query, Request $request): void
    {
        if ($request->filled('id_vendedor')) {
            $query->porVendedor($request->id_vendedor);
        }
        if ($request->filled('origen')) {
            $query->porOrigen($request->origen);
        }
        if ($request->filled('correlativo_referencia')) {
            $query->porCorrelativo($request->correlativo_referencia);
        }
        if ($request->filled('fecha_inicio') || $request->filled('fecha_fin')) {
            $query->entreFechas($request->fecha_inicio, $request->fecha_fin);
        }
    }
}
