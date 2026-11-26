<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProgramadaRequest;
use App\Http\Requests\Ventas\UpdateVentaProgramadaRequest;
use App\Models\Ventas\DetalleVentaProgramada;
use App\Models\Ventas\VentaProgramada;
use App\Services\Ventas\ProgramacionCalendario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VentaProgramadaController extends Controller
{
    private const DETALLE_KEYS = [
        'id_producto',
        'id_presentacion',
        'descripcion',
        'cantidad',
        'precio',
        'precio_sin_iva',
        'precio_con_iva',
        'costo',
        'descuento',
        'sub_total',
        'tipo_gravado',
        'no_sujeta',
        'exenta',
        'gravada',
        'cuenta_a_terceros',
        'total_costo',
        'total',
        'id_vendedor',
        'iva',
        'porcentaje_impuesto',
        'fe_cr_exoneracion',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $items = VentaProgramada::query()
            ->where('id_empresa', Auth::user()->id_empresa)
            ->with(['cliente', 'documento'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('nombre', 'like', $like)
                        ->orWhereHas('cliente', function ($cliente) use ($like) {
                            $cliente->where('nombre', 'like', $like)
                                ->orWhere('apellido', 'like', $like)
                                ->orWhere('nombre_empresa', 'like', $like);
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($request->input('paginate', 15));

        return response()->json($items, 200);
    }

    public function store(StoreVentaProgramadaRequest $request)
    {
        $data = $request->validated();
        $hora = self::normalizarHora($data['hora_ejecucion']);
        $proxima = ProgramacionCalendario::calcularProxima(
            $data['frecuencia'],
            (int) $data['dia_ejecucion'],
            $hora,
            Carbon::now()
        );

        $programada = DB::transaction(function () use ($data, $hora, $proxima) {
            $cabecera = $this->cabeceraAttributes($data, $hora, $proxima);
            $programada = VentaProgramada::create($cabecera);
            $this->syncDetalles($programada, $data['detalles']);

            return $programada;
        });

        return response()->json($programada->load(['detalles', 'cliente', 'documento']), 201);
    }

    public function show($id)
    {
        $programada = $this->findForEmpresa($id);

        return response()->json($programada->load(['detalles', 'cliente', 'documento']), 200);
    }

    public function update(UpdateVentaProgramadaRequest $request, $id)
    {
        $programada = $this->findForEmpresa($id);
        $data = $request->validated();
        $hora = self::normalizarHora($data['hora_ejecucion']);

        $recalcular = $programada->frecuencia !== $data['frecuencia']
            || (int) $programada->dia_ejecucion !== (int) $data['dia_ejecucion']
            || self::normalizarHora((string) $programada->hora_ejecucion) !== $hora;

        DB::transaction(function () use ($programada, $data, $hora, $recalcular) {
            $attrs = $this->cabeceraAttributes($data, $hora, $programada->proxima_ejecucion);
            unset($attrs['id_empresa'], $attrs['id_usuario'], $attrs['ejecuciones_count']);

            if ($recalcular) {
                $attrs['proxima_ejecucion'] = ProgramacionCalendario::calcularProxima(
                    $data['frecuencia'],
                    (int) $data['dia_ejecucion'],
                    $hora,
                    Carbon::now()
                );
            }

            $programada->fill($attrs);
            $programada->save();
            $programada->detalles()->delete();
            $this->syncDetalles($programada, $data['detalles']);
        });

        return response()->json($programada->fresh()->load(['detalles', 'cliente', 'documento']), 200);
    }

    public function activo(Request $request, $id)
    {
        $request->validate(['activo' => 'required|boolean']);

        $programada = $this->findForEmpresa($id);
        $programada->activo = $request->boolean('activo');
        $programada->save();

        return response()->json($programada, 200);
    }

    public function ejecuciones(Request $request, $id)
    {
        $programada = $this->findForEmpresa($id);

        $historial = $programada->ejecuciones()
            ->orderByDesc('id')
            ->paginate($request->input('paginate', 15));

        return response()->json($historial, 200);
    }

    private function findForEmpresa($id): VentaProgramada
    {
        return VentaProgramada::query()
            ->where('id_empresa', Auth::user()->id_empresa)
            ->where('id', $id)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    private function cabeceraAttributes(array $data, string $hora, $proxima): array
    {
        $lineTotal = array_sum(array_map(fn ($d) => (float) ($d['total'] ?? 0), $data['detalles']));

        return [
            'id_empresa' => Auth::user()->id_empresa,
            'nombre' => $data['nombre'],
            'id_cliente' => $data['id_cliente'],
            'id_documento' => $data['id_documento'],
            'id_sucursal' => $data['id_sucursal'] ?? null,
            'id_bodega' => $data['id_bodega'] ?? null,
            'id_vendedor' => $data['id_vendedor'] ?? null,
            'forma_pago' => $data['forma_pago'] ?? null,
            'condicion' => $data['condicion'] ?? null,
            'sub_total' => $data['sub_total'] ?? $lineTotal,
            'descuento' => $data['descuento'] ?? 0,
            'iva' => $data['iva'] ?? 0,
            'total' => $data['total'] ?? $lineTotal,
            'gravada' => $data['gravada'] ?? null,
            'exenta' => $data['exenta'] ?? null,
            'no_sujeta' => $data['no_sujeta'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'frecuencia' => $data['frecuencia'],
            'dia_ejecucion' => (int) $data['dia_ejecucion'],
            'hora_ejecucion' => $hora,
            'proxima_ejecucion' => $proxima,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            'emitir_automatico' => array_key_exists('emitir_automatico', $data)
                ? (bool) $data['emitir_automatico']
                : false,
            'enviar_email' => array_key_exists('enviar_email', $data) ? (bool) $data['enviar_email'] : false,
            'max_ejecuciones' => $data['max_ejecuciones'] ?? null,
            'ejecuciones_count' => 0,
            'id_usuario' => Auth::id(),
        ];
    }

    /** @param list<array<string, mixed>> $detalles */
    private function syncDetalles(VentaProgramada $programada, array $detalles): void
    {
        foreach ($detalles as $linea) {
            $payload = ['id_venta_programada' => $programada->id];
            foreach (self::DETALLE_KEYS as $key) {
                if (array_key_exists($key, $linea)) {
                    $payload[$key] = $linea[$key];
                }
            }
            if (! isset($payload['tipo_gravado'])) {
                $payload['tipo_gravado'] = 'gravada';
            }
            if (! isset($payload['costo'])) {
                $payload['costo'] = 0;
            }
            DetalleVentaProgramada::create($payload);
        }
    }

    private static function normalizarHora(string $hora): string
    {
        $parts = explode(':', $hora);

        return sprintf('%02d:%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
    }
}