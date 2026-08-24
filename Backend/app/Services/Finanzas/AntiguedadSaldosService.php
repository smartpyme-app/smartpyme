<?php

namespace App\Services\Finanzas;

use App\Models\Compras\Compra;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AntiguedadSaldosService
{
    public const BUCKETS = ['0_30', '31_60', '61_90', '91_mas'];

    public const BUCKET_LABELS = [
        '0_30' => '0-30',
        '31_60' => '31-60',
        '61_90' => '61-90',
        '91_mas' => '91+',
    ];

    /**
     * @return array{
     *   tipo: string,
     *   fecha_corte: string,
     *   modo: string,
     *   buckets_activos: list<string>,
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, float>,
     *   entidad: array<string, mixed>|null
     * }
     */
    public function generar(Request $request): array
    {
        $tipo = $request->input('tipo', 'cxc') === 'cxp' ? 'cxp' : 'cxc';
        $fechaCorte = Carbon::parse($request->input('fecha_corte', now()->toDateString()))->startOfDay();
        $bucketsActivos = $this->normalizeBuckets($request->input('buckets'));
        $documentos = $this->documentosPendientes($request, $tipo, $fechaCorte);

        $filasDocs = [];
        foreach ($documentos as $doc) {
            $saldo = $this->saldoDocumento($doc);
            if ($saldo <= 0.009) {
                continue;
            }
            $dias = $this->diasAntiguedad($doc->fecha, $fechaCorte);
            $bucket = $this->bucketPorDias($dias);
            if (! in_array($bucket, $bucketsActivos, true)) {
                continue;
            }
            $filasDocs[] = $this->mapDocumento($doc, $tipo, $saldo, $dias, $bucket);
        }

        $idEntidad = $tipo === 'cxc'
            ? $request->input('id_cliente')
            : $request->input('id_proveedor');

        if ($idEntidad) {
            $totales = $this->emptyBuckets();
            foreach ($filasDocs as $fila) {
                $totales[$fila['bucket']] += $fila['saldo'];
                $totales['total'] += $fila['saldo'];
            }
            foreach ($totales as $k => $v) {
                $totales[$k] = round($v, 2);
            }

            return [
                'tipo' => $tipo,
                'fecha_corte' => $fechaCorte->toDateString(),
                'modo' => 'individual',
                'buckets_activos' => $bucketsActivos,
                'filas' => $filasDocs,
                'totales' => $totales,
                'entidad' => $this->entidadFromFilas($filasDocs, $tipo, (int) $idEntidad),
            ];
        }

        $agrupado = [];
        foreach ($filasDocs as $fila) {
            $key = (string) ($fila['id_entidad'] ?? 0);
            if (! isset($agrupado[$key])) {
                $agrupado[$key] = array_merge($this->emptyBuckets(), [
                    'id_entidad' => $fila['id_entidad'],
                    'nombre' => $fila['nombre_entidad'],
                    'identificacion' => $fila['identificacion'] ?? '',
                    'total' => 0.0,
                ]);
            }
            $agrupado[$key][$fila['bucket']] += $fila['saldo'];
            $agrupado[$key]['total'] += $fila['saldo'];
        }

        $filas = array_values(array_map(function (array $row) {
            foreach (self::BUCKETS as $b) {
                $row[$b] = round($row[$b], 2);
            }
            $row['total'] = round($row['total'], 2);

            return $row;
        }, $agrupado));

        usort($filas, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totales = $this->emptyBuckets();
        foreach ($filas as $fila) {
            foreach (self::BUCKETS as $b) {
                $totales[$b] += $fila[$b];
            }
            $totales['total'] += $fila['total'];
        }
        foreach ($totales as $k => $v) {
            $totales[$k] = round($v, 2);
        }

        return [
            'tipo' => $tipo,
            'fecha_corte' => $fechaCorte->toDateString(),
            'modo' => 'global',
            'buckets_activos' => $bucketsActivos,
            'filas' => $filas,
            'totales' => $totales,
            'entidad' => null,
        ];
    }

    /** @param  list<string>|string|null  $buckets */
    public function normalizeBuckets(array|string|null $buckets): array
    {
        if ($buckets === null || $buckets === '' || $buckets === []) {
            return self::BUCKETS;
        }
        if (is_string($buckets)) {
            $buckets = array_filter(array_map('trim', explode(',', $buckets)));
        }
        $valid = array_values(array_intersect(self::BUCKETS, $buckets));

        return $valid !== [] ? $valid : self::BUCKETS;
    }

    public function bucketPorDias(int $dias): string
    {
        if ($dias <= 30) {
            return '0_30';
        }
        if ($dias <= 60) {
            return '31_60';
        }
        if ($dias <= 90) {
            return '61_90';
        }

        return '91_mas';
    }

    public function diasAntiguedad(mixed $fechaDocumento, Carbon $fechaCorte): int
    {
        if (! $fechaDocumento) {
            return 0;
        }

        return (int) Carbon::parse($fechaDocumento)->startOfDay()->diffInDays($fechaCorte);
    }

    public function saldoDocumento(object $doc): float
    {
        $abonado = round((float) ($doc->abonos_sum_total ?? 0), 2);
        $devoluciones = round((float) ($doc->devoluciones_sum_total ?? 0), 2);

        return round((float) $doc->total - $abonado - $devoluciones, 2);
    }

    /** @return array<string, float> */
    public function emptyBuckets(): array
    {
        return [
            '0_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '91_mas' => 0.0,
            'total' => 0.0,
        ];
    }

    private function documentosPendientes(Request $request, string $tipo, Carbon $fechaCorte): Collection
    {
        if ($tipo === 'cxp') {
            return $this->queryCompras($request, $fechaCorte)->get();
        }

        return $this->queryVentas($request, $fechaCorte)->get();
    }

    private function queryVentas(Request $request, Carbon $fechaCorte)
    {
        return Venta::query()
            ->where('estado', 'Pendiente')
            ->where(function ($q) {
                $q->where('cotizacion', 0)->orWhereNull('cotizacion');
            })
            ->where('fecha', '<=', $fechaCorte->toDateString())
            ->when($request->id_empresa, fn ($q) => $q->where('id_empresa', $request->id_empresa))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->when($request->id_cliente, fn ($q) => $q->where('id_cliente', $request->id_cliente))
            ->when($request->id_vendedor, function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('id_vendedor', $request->id_vendedor)
                        ->orWhereHas('detalles', fn ($d) => $d->where('id_vendedor', $request->id_vendedor));
                });
            })
            ->with(['cliente'])
            ->withSum(['abonos' => fn ($q) => $q->where('estado', 'Confirmado')], 'total')
            ->withSum(['devoluciones' => fn ($q) => $q->where('enable', 1)], 'total')
            ->orderBy('fecha')
            ->orderBy('id');
    }

    private function queryCompras(Request $request, Carbon $fechaCorte)
    {
        return Compra::query()
            ->where('estado', 'Pendiente')
            ->where(function ($q) {
                $q->where('cotizacion', 0)->orWhereNull('cotizacion');
            })
            ->where('fecha', '<=', $fechaCorte->toDateString())
            ->when($request->id_empresa, fn ($q) => $q->where('id_empresa', $request->id_empresa))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->when($request->id_proveedor, fn ($q) => $q->where('id_proveedor', $request->id_proveedor))
            ->with(['proveedor'])
            ->withSum(['abonos' => fn ($q) => $q->where('estado', 'Confirmado')], 'total')
            ->withSum(['devoluciones' => fn ($q) => $q->where('enable', 1)], 'total')
            ->orderBy('fecha')
            ->orderBy('id');
    }

    /** @return array<string, mixed> */
    private function mapDocumento(object $doc, string $tipo, float $saldo, int $dias, string $bucket): array
    {
        if ($tipo === 'cxp') {
            $proveedor = $doc->proveedor;
            $nombre = $proveedor
                ? (($proveedor->tipo ?? '') === 'Empresa' || ! empty($proveedor->nombre_empresa)
                    ? ($proveedor->nombre_empresa ?: $proveedor->nombre)
                    : ($proveedor->nombre ?? 'Proveedor'))
                : 'Proveedor';
            $identificacion = $proveedor->nit ?? $proveedor->ncr ?? '';

            return [
                'id' => $doc->id,
                'id_entidad' => $doc->id_proveedor,
                'nombre_entidad' => $nombre,
                'identificacion' => $identificacion,
                'fecha' => $doc->fecha,
                'fecha_pago' => $doc->fecha_pago,
                'documento' => trim(($doc->tipo_documento ?? 'Compra') . ' #' . ($doc->referencia ?? $doc->id)),
                'correlativo' => $doc->referencia ?? (string) $doc->id,
                'saldo' => $saldo,
                'dias' => $dias,
                'bucket' => $bucket,
                'bucket_label' => self::BUCKET_LABELS[$bucket],
            ];
        }

        $cliente = $doc->cliente;
        $nombre = $doc->nombre_cliente
            ?: ($cliente
                ? (($cliente->tipo ?? '') === 'Empresa'
                    ? ($cliente->nombre_empresa ?: $cliente->nombre)
                    : ($cliente->nombre_completo ?? $cliente->nombre ?? 'Cliente'))
                : 'Consumidor Final');
        $identificacion = $cliente->nit ?? $cliente->ncr ?? $cliente->dui ?? '';

        return [
            'id' => $doc->id,
            'id_entidad' => $doc->id_cliente,
            'nombre_entidad' => $nombre,
            'identificacion' => $identificacion,
            'fecha' => $doc->fecha,
            'fecha_pago' => $doc->fecha_pago,
            'documento' => trim(($doc->nombre_documento ?? $doc->tipo_documento ?? 'Venta') . ' #' . ($doc->correlativo ?? $doc->id)),
            'correlativo' => $doc->correlativo ?? (string) $doc->id,
            'saldo' => $saldo,
            'dias' => $dias,
            'bucket' => $bucket,
            'bucket_label' => self::BUCKET_LABELS[$bucket],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>|null
     */
    private function entidadFromFilas(array $filas, string $tipo, int $idEntidad): ?array
    {
        if ($filas === []) {
            return [
                'id_entidad' => $idEntidad,
                'nombre' => $tipo === 'cxc' ? 'Cliente' : 'Proveedor',
                'identificacion' => '',
            ];
        }

        return [
            'id_entidad' => $filas[0]['id_entidad'],
            'nombre' => $filas[0]['nombre_entidad'],
            'identificacion' => $filas[0]['identificacion'] ?? '',
        ];
    }
}
