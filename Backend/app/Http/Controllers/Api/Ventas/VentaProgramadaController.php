<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProgramadaRequest;
use App\Http\Requests\Ventas\UpdateVentaProgramadaRequest;
use App\Models\Ventas\DetalleVentaProgramada;
use App\Models\Ventas\VentaProgramada;
use App\Models\Ventas\VentaProgramadaEjecucion;
use App\Services\Ventas\EjecutarVentasProgramadasService;
use App\Services\Ventas\ProgramacionCalendario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    /** Campos de cabecera que un PUT puede cambiar (solo si vienen en el request). */
    private const CABECERA_EDITABLE = [
        'id_cliente', 'id_documento', 'id_sucursal', 'id_bodega', 'id_vendedor',
        'forma_pago', 'condicion', 'observaciones', 'frecuencia', 'dia_ejecucion',
        'activo', 'emitir_automatico', 'enviar_email', 'max_ejecuciones',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $items = VentaProgramada::query()
            ->where('id_empresa', Auth::user()->id_empresa)
            ->with(['cliente', 'documento'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->whereHas('cliente', function ($cliente) use ($like) {
                    $cliente->where('nombre', 'like', $like)
                        ->orWhere('apellido', 'like', $like)
                        ->orWhere('nombre_empresa', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate($request->input('paginate', 15));

        return response()->json($items, 200);
    }

    public function store(StoreVentaProgramadaRequest $request)
    {
        $data = $request->validated();
        $data['detalles'] = array_map([self::class, 'normalizarLinea'], $data['detalles']);
        $hora = self::normalizarHora($data['hora_ejecucion']);
        $proxima = ProgramacionCalendario::calcularProxima(
            $data['frecuencia'],
            (int) $data['dia_ejecucion'],
            $hora,
            Carbon::now()
        );

        $programada = DB::transaction(function () use ($data, $hora, $proxima) {
            $cabecera = $this->cabeceraAttributes($data, $hora, $this->resolverProxima($data, $hora, $proxima));
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

        $frecuencia = $data['frecuencia'] ?? $programada->frecuencia;
        $hora = isset($data['hora_ejecucion'])
            ? self::normalizarHora($data['hora_ejecucion'])
            : self::normalizarHora((string) $programada->hora_ejecucion);
        $dia = (int) ($data['dia_ejecucion'] ?? $programada->dia_ejecucion);
        if (! empty($data['proxima_ejecucion'])) {
            $dia = Carbon::parse($data['proxima_ejecucion'])->day;
            $data['dia_ejecucion'] = $dia;
        }

        $recalcular = $programada->frecuencia !== $frecuencia
            || (int) $programada->dia_ejecucion !== $dia
            || self::normalizarHora((string) $programada->hora_ejecucion) !== $hora
            || array_key_exists('proxima_ejecucion', $data);

        DB::transaction(function () use ($programada, $data, $frecuencia, $dia, $hora, $recalcular) {
            // Solo se llenan las llaves presentes en el request (PUT parcial no resetea el resto).
            $attrs = Arr::only($data, self::CABECERA_EDITABLE);
            foreach (['id_sucursal', 'id_bodega', 'id_vendedor'] as $k) {
                if (array_key_exists($k, $attrs) && $attrs[$k] === null) {
                    unset($attrs[$k]); // no se borran con null
                }
            }
            foreach (['activo', 'emitir_automatico', 'enviar_email'] as $k) {
                if (array_key_exists($k, $attrs)) {
                    $attrs[$k] = (bool) $attrs[$k];
                }
            }
            $attrs['dia_ejecucion'] = $dia;
            if (isset($data['hora_ejecucion'])) {
                $attrs['hora_ejecucion'] = $hora;
            }

            if ($recalcular) {
                $fallback = ProgramacionCalendario::calcularProxima(
                    $frecuencia, $dia, $hora, Carbon::now()
                );
                $attrs['proxima_ejecucion'] = $this->resolverProxima($data, $hora, $fallback);
            }

            if (isset($data['detalles'])) {
                $antiguos = $programada->detalles()->get()->values();
                $detalles = $this->mergeDetalles($antiguos->all(), $data['detalles']);
                $attrs = array_merge($attrs, self::totalesDesdeDetalles($detalles));
                $programada->detalles()->delete();
                $this->syncDetalles($programada, $detalles);
            }

            $programada->fill($attrs);
            $programada->save();
        });

        return response()->json($programada->fresh()->load(['detalles', 'cliente', 'documento']), 200);
    }

    public function activo(Request $request, $id)
    {
        $request->validate(['activo' => 'required|boolean']);

        $programada = $this->findForEmpresa($id);
        $programada->activo = $request->boolean('activo');
        // Al reactivar, no dejar un slot vencido: el cron dispararía una factura inmediata.
        if ($programada->activo && $programada->proxima_ejecucion->lessThanOrEqualTo(Carbon::now())) {
            $programada->proxima_ejecucion = ProgramacionCalendario::calcularProxima(
                $programada->frecuencia,
                (int) $programada->dia_ejecucion,
                (string) $programada->hora_ejecucion,
                Carbon::now()
            );
        }
        $programada->save();

        return response()->json($programada, 200);
    }

    public function ejecuciones(Request $request, $id)
    {
        $programada = $this->findForEmpresa($id);

        $historial = $programada->ejecuciones()
            ->with(['venta:id,correlativo,id_documento', 'venta.documento:id,nombre'])
            ->orderByDesc('id')
            ->paginate($request->input('paginate', 15));

        return response()->json($historial, 200);
    }

    /**
     * Reintenta de inmediato una ejecución en error (actualiza esa fila; no inserta otra).
     */
    public function reintentar(Request $request, $id, EjecutarVentasProgramadasService $service)
    {
        $request->validate([
            'id_ejecucion' => 'nullable|integer|min:1',
        ]);

        $programada = $this->findForEmpresa($id);
        $ejecucionQuery = VentaProgramadaEjecucion::query()
            ->where('id_venta_programada', $programada->id)
            ->where('estado', 'error');

        if ($request->filled('id_ejecucion')) {
            $ejecucion = (clone $ejecucionQuery)->where('id', (int) $request->input('id_ejecucion'))->first();
        } else {
            $ejecucion = $ejecucionQuery->orderByDesc('id')->first();
        }

        if (!$ejecucion) {
            return response()->json(['error' => 'No hay una ejecución en error para reintentar.'], 422);
        }

        try {
            $ejecucion = $service->reintentar($programada, $ejecucion, Carbon::now());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'programada' => $programada->fresh()->load(['detalles', 'cliente', 'documento']),
            'ejecucion' => $ejecucion->load(['venta:id,correlativo,id_documento', 'venta.documento:id,nombre']),
        ], 200);
    }

    private function findForEmpresa($id): VentaProgramada
    {
        return VentaProgramada::query()
            ->where('id_empresa', Auth::user()->id_empresa)
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Si el cliente manda fecha de próxima ejecución, se usa (con la hora); si no, el fallback del calendario.
     * @param array<string, mixed> $data
     */
    private function resolverProxima(array $data, string $hora, Carbon $fallback): Carbon
    {
        if (empty($data['proxima_ejecucion'])) {
            return $fallback;
        }
        $parts = array_pad(explode(':', $hora), 3, '0');

        return Carbon::parse($data['proxima_ejecucion'])
            ->setTime((int) $parts[0], (int) $parts[1], (int) $parts[2]);
    }

    /** @param array<string, mixed> $data */
    private function cabeceraAttributes(array $data, string $hora, $proxima): array
    {
        $user = Auth::user();
        $dia = ! empty($data['proxima_ejecucion'])
            ? Carbon::parse($data['proxima_ejecucion'])->day
            : (int) $data['dia_ejecucion'];

        return self::totalesDesdeDetalles($data['detalles']) + [
            'id_empresa' => $user->id_empresa,
            'id_cliente' => $data['id_cliente'],
            'id_documento' => $data['id_documento'],
            // Si el form no los manda, se toman los defaults del usuario (sucursal/bodega activa).
            'id_sucursal' => $data['id_sucursal'] ?? $user->id_sucursal,
            'id_bodega' => $data['id_bodega'] ?? $user->id_bodega,
            'id_vendedor' => $data['id_vendedor'] ?? $user->id,
            'forma_pago' => $data['forma_pago'] ?? 'Efectivo',
            'condicion' => $data['condicion'] ?? 'Contado',
            'observaciones' => $data['observaciones'] ?? null,
            'frecuencia' => $data['frecuencia'],
            'dia_ejecucion' => $dia,
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

    /**
     * Desglose fiscal de la línea: si el cliente no lo manda, se deriva de tipo_gravado/total.
     * @param array<string, mixed> $l @return array<string, mixed>
     */
    private static function normalizarLinea(array $l): array
    {
        $l['tipo_gravado'] = $l['tipo_gravado'] ?? 'gravada';
        if (! isset($l['gravada']) && ! isset($l['exenta']) && ! isset($l['no_sujeta'])) {
            $campo = ['exenta' => 'exenta', 'no_sujeta' => 'no_sujeta'][$l['tipo_gravado']] ?? 'gravada';
            $l[$campo] = $l['total'];
        }
        $l['sub_total'] = $l['sub_total'] ?? $l['total'];

        return $l;
    }

    /**
     * Cabecera coherente con las líneas: total = suma de netos + IVA.
     * @param list<array<string, mixed>> $detalles @return array<string, float>
     */
    private static function totalesDesdeDetalles(array $detalles): array
    {
        $sum = fn (string $k) => round(array_sum(array_map(fn ($d) => (float) ($d[$k] ?? 0), $detalles)), 2);
        $neto = $sum('total');
        $iva = $sum('iva');

        return [
            'sub_total' => $sum('sub_total') ?: $neto,
            'descuento' => $sum('descuento'),
            'iva' => $iva,
            'gravada' => $sum('gravada'),
            'exenta' => $sum('exenta'),
            'no_sujeta' => $sum('no_sujeta'),
            'total' => round($neto + $iva, 2),
        ];
    }

    /**
     * Completa lo que el form no envía con la línea anterior (misma posición y producto).
     * Montos calculados solo se heredan si cantidad y precio no cambiaron.
     * @param list<DetalleVentaProgramada> $antiguos @param list<array<string, mixed>> $nuevos
     * @return list<array<string, mixed>>
     */
    private function mergeDetalles(array $antiguos, array $nuevos): array
    {
        $calculados = ['sub_total', 'gravada', 'exenta', 'no_sujeta', 'iva', 'precio_sin_iva', 'precio_con_iva', 'total_costo'];
        $out = [];
        foreach ($nuevos as $i => $linea) {
            $old = $antiguos[$i] ?? null;
            if ($old && (int) $old->id_producto === (int) $linea['id_producto']) {
                $base = array_filter($old->only(self::DETALLE_KEYS), fn ($v) => $v !== null);
                $igual = (float) $old->cantidad === (float) $linea['cantidad'] && (float) $old->precio === (float) $linea['precio'];
                if (! $igual) {
                    $base = array_diff_key($base, array_flip($calculados));
                }
                $linea += $base;
            }
            $out[] = self::normalizarLinea($linea);
        }

        return $out;
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