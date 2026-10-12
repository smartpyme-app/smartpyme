<?php

namespace App\Services\Contadores;

use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaCrController;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaHdController;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaLegacyController;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaResumenController;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaSvController;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Partidas\Partida;
use App\Services\Contabilidad\LibrosIva\LibroIvaPaisResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContadorLibrosIvaService
{
    /** @var list<array{titulo: string, informes: list<array{clave: string, titulo: string, descripcion: string}>}> */
    public const GRUPOS_SV = [
        [
            'titulo' => 'Libros de IVA',
            'informes' => [
                ['clave' => 'compras_libro', 'titulo' => 'Libro de compras', 'descripcion' => 'Compras y gastos con crédito fiscal'],
                ['clave' => 'ventas_contribuyentes_libro', 'titulo' => 'Libro de ventas a contribuyentes', 'descripcion' => 'Crédito fiscal emitido a contribuyentes'],
                ['clave' => 'ventas_consumidor_libro', 'titulo' => 'Libro de ventas a consumidores finales', 'descripcion' => 'Resumen diario de ventas'],
            ],
        ],
        [
            'titulo' => 'Anexos para el F-07',
            'informes' => [
                ['clave' => 'compras_anexo', 'titulo' => 'Anexo de compras', 'descripcion' => 'CSV para declaración MH'],
                ['clave' => 'ventas_contribuyentes_anexo', 'titulo' => 'Anexo de ventas a contribuyentes', 'descripcion' => 'CSV para declaración MH'],
                ['clave' => 'ventas_consumidor_anexo', 'titulo' => 'Anexo de ventas a consumidores finales', 'descripcion' => 'CSV para declaración MH'],
            ],
        ],
        [
            'titulo' => 'Retenciones y sujetos excluidos',
            'informes' => [
                ['clave' => 'retencion_percepcion_libro', 'titulo' => 'Retenciones y percepciones de IVA', 'descripcion' => 'Libro retención/percepción 1%'],
                ['clave' => 'sujetos_excluidos_libro', 'titulo' => 'Facturas de sujeto excluido', 'descripcion' => 'Compras a sujetos excluidos'],
            ],
        ],
        [
            'titulo' => 'Resumen y partida',
            'informes' => [
                ['clave' => 'resumen_iva', 'titulo' => 'Resumen de IVA del período', 'descripcion' => 'Totalización débito, crédito e IVA a pagar'],
                ['clave' => 'partida_iva', 'titulo' => 'Partida contable de IVA', 'descripcion' => 'Partidas del período relacionadas con IVA'],
            ],
        ],
    ];

    /** @var list<array{titulo: string, informes: list<array{clave: string, titulo: string, descripcion: string}>}> */
    public const GRUPOS_CR = [
        [
            'titulo' => 'Libros de IVA',
            'informes' => [
                ['clave' => 'cr_compras_detalle', 'titulo' => 'Detalle IVA compras', 'descripcion' => 'Reporte fiscal de compras (Hacienda CR)'],
                ['clave' => 'cr_ventas_detalle', 'titulo' => 'Detalle IVA ventas', 'descripcion' => 'Reporte fiscal de ventas (Hacienda CR)'],
            ],
        ],
        [
            'titulo' => 'Resumen y partida',
            'informes' => [
                ['clave' => 'resumen_iva', 'titulo' => 'Resumen de IVA del período', 'descripcion' => 'Débito, crédito e IVA estimado del período'],
                ['clave' => 'partida_iva', 'titulo' => 'Partida contable de IVA', 'descripcion' => 'Partidas del período relacionadas con IVA'],
            ],
        ],
    ];

    /** @var list<array{titulo: string, informes: list<array{clave: string, titulo: string, descripcion: string}>}> */
    public const GRUPOS_HD = [
        [
            'titulo' => 'Libros de IVA',
            'informes' => [
                ['clave' => 'compras_libro', 'titulo' => 'Libro de compras', 'descripcion' => 'Compras del período'],
                ['clave' => 'ventas_contribuyentes_libro', 'titulo' => 'Libro de ventas a contribuyentes', 'descripcion' => 'Ventas B2B'],
                ['clave' => 'ventas_consumidor_libro', 'titulo' => 'Libro de ventas a consumidor final', 'descripcion' => 'Ventas al consumidor'],
            ],
        ],
        [
            'titulo' => 'Retenciones',
            'informes' => [
                ['clave' => 'retenciones_libro', 'titulo' => 'Retenciones de IVA', 'descripcion' => 'Retenciones en ventas'],
            ],
        ],
        [
            'titulo' => 'Resumen y partida',
            'informes' => [
                ['clave' => 'resumen_iva', 'titulo' => 'Resumen de IVA del período', 'descripcion' => 'Totalización del período'],
                ['clave' => 'partida_iva', 'titulo' => 'Partida contable de IVA', 'descripcion' => 'Partidas del período relacionadas con IVA'],
            ],
        ],
    ];

    /** @var list<array{titulo: string, informes: list<array{clave: string, titulo: string, descripcion: string}>}> */
    public const GRUPOS_GENERAL = [
        [
            'titulo' => 'Libros de IVA',
            'informes' => [
                ['clave' => 'compras_libro', 'titulo' => 'Libro de compras', 'descripcion' => 'Compras del período'],
                ['clave' => 'ventas_consumidor_libro', 'titulo' => 'Libro de ventas', 'descripcion' => 'Ventas del período'],
            ],
        ],
        [
            'titulo' => 'Resumen y partida',
            'informes' => [
                ['clave' => 'resumen_iva', 'titulo' => 'Resumen de IVA del período', 'descripcion' => 'Totalización del período'],
                ['clave' => 'partida_iva', 'titulo' => 'Partida contable de IVA', 'descripcion' => 'Partidas del período relacionadas con IVA'],
            ],
        ],
    ];

    public function __construct(
        private ContadorCarteraMetricasService $metricas,
        private LibroIvaPaisResolver $paisResolver,
    ) {}

    /** @return array<string, mixed> */
    public function vista(int $idEmpresa, int $anio, int $mes): array
    {
        $empresa = Empresa::query()->select(['id', 'nombre', 'logo', 'giro', 'pais'])->findOrFail($idEmpresa);
        $moduloPais = $this->paisResolver->tipo($empresa);
        $catalogo = $this->catalogoGrupos($moduloPais);

        Carbon::setLocale('es');
        $mesNombre = ucfirst(Carbon::create($anio, $mes, 1)->translatedFormat('F'));
        $detalle = $this->metricas->detalleEmpresa($idEmpresa, $anio, $mes);
        $f07 = $detalle['impuestos']['f07'];

        $grupos = [];
        foreach ($catalogo as $grupo) {
            $informes = [];
            foreach ($grupo['informes'] as $item) {
                $informes[] = array_merge($item, [
                    'total_documentos' => $this->contarInforme($idEmpresa, $anio, $mes, $item['clave']),
                    'soporta_pdf' => $this->soportaPdf($item['clave'], $moduloPais),
                    'soporta_excel' => $this->soportaExcel($item['clave'], $moduloPais),
                    'soporta_csv' => $this->soportaCsv($item['clave'], $moduloPais),
                ]);
            }
            $grupos[] = ['titulo' => $grupo['titulo'], 'informes' => $informes];
        }

        return [
            'empresa' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'logo' => $empresa->logo,
                'giro' => $empresa->giro,
                'pais' => $empresa->pais,
            ],
            'modulo_pais' => $moduloPais,
            'informe_default' => $this->informeDefault($moduloPais),
            'periodo' => ['mes' => $mes, 'anio' => $anio, 'label' => "{$mesNombre} {$anio}"],
            'resumen' => [
                'debito_fiscal' => (float) $f07['debito_fiscal'],
                'credito_fiscal' => (float) $f07['credito_fiscal'],
                'retenciones' => (float) $f07['retenciones'],
                'iva_a_pagar' => (float) $f07['iva_a_pagar'],
            ],
            'grupos' => $grupos,
            'actualizado_hoy' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function informe(int $idEmpresa, int $anio, int $mes, string $clave, int $limit = 50): array
    {
        return ContadorEmpresaContext::run($idEmpresa, function () use ($idEmpresa, $anio, $mes, $clave, $limit) {
            $meta = $this->metaInforme($idEmpresa, $clave);
            $preview = $this->previewInforme($idEmpresa, $clave, $anio, $mes, $limit);

            return array_merge($meta, $preview);
        });
    }

    public function exportar(int $idEmpresa, int $anio, int $mes, string $clave, string $formato): Response
    {
        $empresa = Empresa::query()->findOrFail($idEmpresa);
        $moduloPais = $this->paisResolver->tipo($empresa);

        return ContadorEmpresaContext::run($idEmpresa, function () use ($moduloPais, $anio, $mes, $clave, $formato) {
            $req = $this->libroRequest($anio, $mes);

            return match ($moduloPais) {
                LibroIvaPaisResolver::TIPO_CR => $this->exportarCr($clave, $formato, $req),
                LibroIvaPaisResolver::TIPO_HD => $this->exportarHd($clave, $formato, $anio, $mes, $req),
                LibroIvaPaisResolver::TIPO_GENERAL => $this->exportarGeneral($clave, $formato, $req),
                default => $this->exportarSv($clave, $formato, $anio, $mes, $req),
            };
        });
    }

    private function contarInforme(int $idEmpresa, int $anio, int $mes, string $clave): int
    {
        try {
            $preview = ContadorEmpresaContext::run(
                $idEmpresa,
                fn () => $this->previewInforme($idEmpresa, $clave, $anio, $mes, PHP_INT_MAX)
            );

            return (int) ($preview['total'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{clave: string, titulo: string, descripcion: string} */
    private function metaInforme(int $idEmpresa, string $clave): array
    {
        $empresa = Empresa::query()->findOrFail($idEmpresa);
        foreach ($this->catalogoGrupos($this->paisResolver->tipo($empresa)) as $grupo) {
            foreach ($grupo['informes'] as $item) {
                if ($item['clave'] === $clave) {
                    return $item;
                }
            }
        }

        throw new \InvalidArgumentException('Informe no válido.', 422);
    }

    /** @return array<string, mixed> */
    private function previewInforme(int $idEmpresa, string $clave, int $anio, int $mes, int $limit): array
    {
        $empresa = Empresa::query()->findOrFail($idEmpresa);
        $tipo = $this->paisResolver->tipo($empresa);

        if ($tipo === LibroIvaPaisResolver::TIPO_CR) {
            return match ($clave) {
                'cr_ventas_detalle' => $this->previewCrDetalle($anio, $mes, $limit, true),
                'cr_compras_detalle' => $this->previewCrDetalle($anio, $mes, $limit, false),
                'resumen_iva' => $this->previewResumenIva($anio, $mes),
                'partida_iva' => $this->previewPartidasIva($anio, $mes, $limit),
                default => $this->previewSinDetalle($clave, 'Informe no disponible para Costa Rica.'),
            };
        }

        if ($tipo === LibroIvaPaisResolver::TIPO_HD) {
            return match ($clave) {
                'compras_libro' => $this->previewLegacyFilas($anio, $mes, $limit, 'compras'),
                'ventas_contribuyentes_libro' => $this->previewHdContribuyentes($anio, $mes, $limit),
                'ventas_consumidor_libro' => $this->previewLegacyFilas($anio, $mes, $limit, 'consumidores'),
                'retenciones_libro' => $this->previewSinDetalle($clave, 'Descargue Excel para ver retenciones.'),
                'resumen_iva' => $this->previewResumenIva($anio, $mes),
                'partida_iva' => $this->previewPartidasIva($anio, $mes, $limit),
                default => $this->previewSinDetalle($clave, 'Vista previa no disponible.'),
            };
        }

        if ($tipo === LibroIvaPaisResolver::TIPO_GENERAL) {
            return match ($clave) {
                'compras_libro' => $this->previewLegacyFilas($anio, $mes, $limit, 'compras'),
                'ventas_consumidor_libro' => $this->previewLegacyFilas($anio, $mes, $limit, 'consumidores'),
                'resumen_iva' => $this->previewResumenIva($anio, $mes),
                'partida_iva' => $this->previewPartidasIva($anio, $mes, $limit),
                default => $this->previewSinDetalle($clave, 'Vista previa no disponible.'),
            };
        }

        return match ($clave) {
            'compras_libro', 'compras_anexo' => $this->previewCompras($anio, $mes, $limit),
            'ventas_contribuyentes_libro', 'ventas_contribuyentes_anexo' => $this->previewVentasContribuyentes($anio, $mes, $limit),
            'ventas_consumidor_libro', 'ventas_consumidor_anexo' => $this->previewVentasConsumidor($anio, $mes, $limit),
            'sujetos_excluidos_libro' => $this->previewSujetosExcluidos($anio, $mes, $limit),
            'resumen_iva' => $this->previewResumenIva($anio, $mes),
            'partida_iva' => $this->previewPartidasIva($anio, $mes, $limit),
            'retencion_percepcion_libro' => $this->previewSinDetalle($clave, 'Descargue el libro Excel para ver el detalle de retenciones y percepciones.'),
            default => $this->previewSinDetalle($clave, 'Vista previa no disponible.'),
        };
    }

    /** @return array<string, mixed> */
    private function previewCompras(int $anio, int $mes, int $limit): array
    {
        $controller = app(LibrosIvaSvController::class);
        $data = $controller->compras($this->libroRequest($anio, $mes))->getData(true);
        if (!is_array($data)) {
            $data = [];
        }
        $total = count($data);
        $slice = array_slice($data, 0, $limit);
        $filas = array_map(fn ($row) => [
            'fecha' => $row['fecha'] ?? '',
            'documento' => $row['num_documento'] ?? '',
            'proveedor' => $row['nombre_proveedor'] ?? '',
            'gravada' => round((float) (($row['compras_gravadas'] ?? 0) + ($row['importaciones_gravadas'] ?? 0)), 2),
            'iva_credito' => round((float) ($row['credito_fiscal'] ?? 0), 2),
            'total' => round((float) ($row['total'] ?? 0), 2),
        ], $slice);

        $totales = [
            'gravada' => round(array_sum(array_column($filas, 'gravada')), 2),
            'iva_credito' => round(array_sum(array_column($filas, 'iva_credito')), 2),
            'total' => round(array_sum(array_column($filas, 'total')), 2),
        ];

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'documento', 'label' => 'Documento'],
                ['key' => 'proveedor', 'label' => 'Proveedor'],
                ['key' => 'gravada', 'label' => 'Gravada', 'numeric' => true],
                ['key' => 'iva_credito', 'label' => 'IVA crédito', 'numeric' => true],
                ['key' => 'total', 'label' => 'Total', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => $totales,
        ];
    }

    /** @return array<string, mixed> */
    private function previewVentasContribuyentes(int $anio, int $mes, int $limit): array
    {
        $data = app(LibrosIvaSvController::class)
            ->contribuyentes($this->libroRequest($anio, $mes))
            ->getData(true);
        if (!is_array($data)) {
            $data = [];
        }
        $total = count($data);
        $slice = array_slice($data, 0, $limit);
        $filas = array_map(fn ($row) => [
            'fecha' => $row['fecha'] ?? '',
            'documento' => $row['correlativo'] ?? ($row['codigo_generacion'] ?? ''),
            'cliente' => $row['nombre_cliente'] ?? '',
            'gravada' => round((float) ($row['ventas_internas_gravadas'] ?? 0), 2),
            'debito_fiscal' => round((float) ($row['debito_fiscal'] ?? 0), 2),
            'total' => round((float) ($row['total'] ?? 0), 2),
        ], $slice);

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'documento', 'label' => 'Documento'],
                ['key' => 'cliente', 'label' => 'Cliente'],
                ['key' => 'gravada', 'label' => 'Gravada', 'numeric' => true],
                ['key' => 'debito_fiscal', 'label' => 'Débito fiscal', 'numeric' => true],
                ['key' => 'total', 'label' => 'Total', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => [
                'gravada' => round(array_sum(array_column($filas, 'gravada')), 2),
                'debito_fiscal' => round(array_sum(array_column($filas, 'debito_fiscal')), 2),
                'total' => round(array_sum(array_column($filas, 'total')), 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function previewVentasConsumidor(int $anio, int $mes, int $limit): array
    {
        $data = app(LibrosIvaSvController::class)
            ->consumidores($this->libroRequest($anio, $mes))
            ->getData(true);
        if (!is_array($data)) {
            $data = [];
        }
        $total = count($data);
        $slice = array_slice($data, 0, $limit);
        $filas = array_map(fn ($row) => [
            'fecha' => $row['fecha'] ?? '',
            'correlativo_inicial' => $row['correlativo_inicial'] ?? '',
            'correlativo_final' => $row['correlativo_final'] ?? '',
            'gravada' => round((float) ($row['ventas_internas_gravadas'] ?? 0), 2),
            'total_diario' => round((float) ($row['total_ventas_diarias_propias'] ?? 0), 2),
        ], $slice);

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'correlativo_inicial', 'label' => 'Del'],
                ['key' => 'correlativo_final', 'label' => 'Al'],
                ['key' => 'gravada', 'label' => 'Gravada', 'numeric' => true],
                ['key' => 'total_diario', 'label' => 'Total diario', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => [
                'gravada' => round(array_sum(array_column($filas, 'gravada')), 2),
                'total_diario' => round(array_sum(array_column($filas, 'total_diario')), 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function previewSujetosExcluidos(int $anio, int $mes, int $limit): array
    {
        $data = app(LibrosIvaSvController::class)
            ->comprasSujetosExcluidos($this->libroRequest($anio, $mes))
            ->getData(true);
        if (!is_array($data)) {
            $data = [];
        }
        $total = count($data);
        $slice = array_slice($data, 0, $limit);
        $filas = array_map(fn ($row) => [
            'fecha' => $row['fecha'] ?? '',
            'documento' => $row['referencia'] ?? ($row['num_documento'] ?? ''),
            'proveedor' => $row['proveedor'] ?? ($row['nombre_proveedor'] ?? ''),
            'total' => round((float) ($row['total'] ?? 0), 2),
        ], $slice);

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'documento', 'label' => 'Documento'],
                ['key' => 'proveedor', 'label' => 'Proveedor'],
                ['key' => 'total', 'label' => 'Total', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => ['total' => round(array_sum(array_column($filas, 'total')), 2)],
        ];
    }

    /** @return array<string, mixed> */
    private function previewResumenIva(int $anio, int $mes): array
    {
        $idEmpresa = (int) auth()->user()->id_empresa;
        $detalle = $this->metricas->detalleEmpresa($idEmpresa, $anio, $mes);
        $f07 = $detalle['impuestos']['f07'];
        $filas = [
            ['concepto' => 'Débito fiscal', 'monto' => (float) $f07['debito_fiscal']],
            ['concepto' => 'Crédito fiscal', 'monto' => (float) $f07['credito_fiscal']],
            ['concepto' => 'Retenciones y percepciones', 'monto' => (float) $f07['retenciones']],
            ['concepto' => 'IVA a pagar', 'monto' => (float) $f07['iva_a_pagar']],
        ];

        return [
            'columnas' => [
                ['key' => 'concepto', 'label' => 'Concepto'],
                ['key' => 'monto', 'label' => 'Monto', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => count($filas),
            'mostrando' => count($filas),
            'totales' => ['monto' => (float) $f07['iva_a_pagar']],
        ];
    }

    /** @return array<string, mixed> */
    private function previewPartidasIva(int $anio, int $mes, int $limit): array
    {
        [$desde, $hasta] = $this->rangoMes($anio, $mes);
        $idEmpresa = (int) auth()->user()->id_empresa;
        $rows = Partida::query()
            ->where('id_empresa', $idEmpresa)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function ($q) {
                $q->where('concepto', 'like', '%IVA%')
                    ->orWhere('concepto', 'like', '%iva%')
                    ->orWhere('tipo', 'like', '%IVA%');
            })
            ->orderByDesc('fecha')
            ->limit($limit)
            ->get(['id', 'fecha', 'correlativo', 'concepto', 'estado']);

        $filas = $rows->map(fn (Partida $p) => [
            'fecha' => $p->fecha,
            'correlativo' => $p->correlativo,
            'concepto' => $p->concepto,
            'estado' => $p->estado,
        ])->all();

        $total = Partida::query()
            ->where('id_empresa', $idEmpresa)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function ($q) {
                $q->where('concepto', 'like', '%IVA%')
                    ->orWhere('concepto', 'like', '%iva%')
                    ->orWhere('tipo', 'like', '%IVA%');
            })
            ->count();

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'correlativo', 'label' => 'Correlativo'],
                ['key' => 'concepto', 'label' => 'Concepto'],
                ['key' => 'estado', 'label' => 'Estado'],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function previewSinDetalle(string $clave, string $nota): array
    {
        return [
            'columnas' => [],
            'filas' => [],
            'total' => 0,
            'mostrando' => 0,
            'totales' => [],
            'nota' => $nota,
        ];
    }

    /** @return list<array{titulo: string, informes: list<array{clave: string, titulo: string, descripcion: string}>}> */
    private function catalogoGrupos(string $moduloPais): array
    {
        return match ($moduloPais) {
            LibroIvaPaisResolver::TIPO_CR => self::GRUPOS_CR,
            LibroIvaPaisResolver::TIPO_HD => self::GRUPOS_HD,
            LibroIvaPaisResolver::TIPO_GENERAL => self::GRUPOS_GENERAL,
            default => self::GRUPOS_SV,
        };
    }

    private function informeDefault(string $moduloPais): string
    {
        return match ($moduloPais) {
            LibroIvaPaisResolver::TIPO_CR => 'cr_compras_detalle',
            default => 'compras_libro',
        };
    }

    private function exportarSv(string $clave, string $formato, int $anio, int $mes, BaseLibroIVARequest $req): Response
    {
        if ($formato === 'csv') {
            $formato = 'excel';
        }
        $controller = app(LibrosIvaSvController::class);

        return match ($clave) {
            'compras_libro' => $formato === 'pdf'
                ? $controller->compras($this->libroRequest($anio, $mes, ['formato' => 'pdf']))
                : $controller->comprasLibroExport($req),
            'compras_anexo' => $controller->comprasAnexoExport($req),
            'ventas_contribuyentes_libro' => $formato === 'pdf'
                ? $controller->contribuyentes($this->libroRequest($anio, $mes, ['formato' => 'pdf']))
                : $controller->contribuyentesLibroExport($req),
            'ventas_contribuyentes_anexo' => $controller->contribuyentesAnexoExport($req),
            'ventas_consumidor_libro' => $formato === 'pdf'
                ? $controller->consumidores($this->libroRequest($anio, $mes, ['formato' => 'pdf']))
                : $controller->consumidoresLibroExport($req),
            'ventas_consumidor_anexo' => $controller->consumidoresAnexoExport($req),
            'retencion_percepcion_libro' => $formato === 'pdf'
                ? throw new \InvalidArgumentException('PDF no disponible para este informe.', 422)
                : $controller->libroRetencion1Export($req),
            'sujetos_excluidos_libro' => $formato === 'pdf'
                ? throw new \InvalidArgumentException('PDF no disponible para este informe.', 422)
                : $controller->comprasSujetosExcluidosLibroExport($req),
            'resumen_iva' => app(LibrosIvaResumenController::class)->resumenFiscalExport($req),
            default => throw new \InvalidArgumentException('Exportación no disponible para este informe.', 422),
        };
    }

    private function exportarCr(string $clave, string $formato, BaseLibroIVARequest $req): Response
    {
        $cr = app(LibrosIvaCrController::class);

        return match ($clave) {
            'cr_ventas_detalle' => match ($formato) {
                'pdf' => $cr->reporteDetalleIvaVentasPdf($req),
                'csv' => $cr->reporteDetalleIvaVentasCsv($req),
                default => $cr->reporteDetalleIvaVentasExcel($req),
            },
            'cr_compras_detalle' => match ($formato) {
                'pdf' => $cr->reporteDetalleIvaComprasPdf($req),
                'csv' => $cr->reporteDetalleIvaComprasCsv($req),
                default => $cr->reporteDetalleIvaComprasExcel($req),
            },
            'resumen_iva' => app(LibrosIvaResumenController::class)->resumenFiscalExport($req),
            default => throw new \InvalidArgumentException('Exportación no disponible para este informe.', 422),
        };
    }

    private function exportarHd(string $clave, string $formato, int $anio, int $mes, BaseLibroIVARequest $req): Response
    {
        if ($formato === 'csv') {
            $formato = 'excel';
        }
        $hd = app(LibrosIvaHdController::class);

        return match ($clave) {
            'compras_libro' => $formato === 'pdf'
                ? $hd->compras($this->libroRequest($anio, $mes, ['formato' => 'pdf']))
                : $hd->comprasLibroExport($req),
            'ventas_contribuyentes_libro' => $formato === 'pdf'
                ? $hd->contribuyentes($this->libroRequest($anio, $mes, ['formato' => 'pdf']))
                : $hd->contribuyentesLibroExport($req),
            'ventas_consumidor_libro' => $formato === 'pdf'
                ? $hd->consumidores($this->libroRequest($anio, $mes, ['formato' => 'pdf']))
                : $hd->consumidoresLibroExport($req),
            'resumen_iva' => app(LibrosIvaResumenController::class)->resumenFiscalExport($req),
            default => throw new \InvalidArgumentException('Exportación no disponible para este informe.', 422),
        };
    }

    private function exportarGeneral(string $clave, string $formato, BaseLibroIVARequest $req): Response
    {
        if ($formato === 'csv') {
            $formato = 'excel';
        }
        $legacy = app(LibrosIvaLegacyController::class);

        return match ($clave) {
            'compras_libro' => $legacy->comprasLibroExport($req),
            'ventas_consumidor_libro' => $legacy->consumidoresLibroExport($req),
            'resumen_iva' => app(LibrosIvaResumenController::class)->resumenFiscalExport($req),
            default => throw new \InvalidArgumentException('Exportación no disponible para este informe.', 422),
        };
    }

    /** @return array<string, mixed> */
    private function previewCrDetalle(int $anio, int $mes, int $limit, bool $ventas): array
    {
        $cr = app(LibrosIvaCrController::class);
        $req = $this->libroRequest($anio, $mes);
        $payload = ($ventas ? $cr->reporteDetalleIvaVentas($req) : $cr->reporteDetalleIvaCompras($req))->getData(true);
        $rows = is_array($payload['filas'] ?? null) ? $payload['filas'] : [];
        $total = count($rows);
        $slice = array_slice($rows, 0, $limit);
        $filas = array_map(function (array $row) use ($ventas) {
            $iva = (float) ($row['iva_13'] ?? 0)
                + (float) ($row['iva_8'] ?? 0)
                + (float) ($row['iva_4'] ?? 0)
                + (float) ($row['iva_2'] ?? 0)
                + (float) ($row['iva_1'] ?? 0);

            return [
                'fecha' => $row['fecha'] ?? '',
                'documento' => $row['folio'] ?? ($row['clave'] ?? ''),
                'tercero' => $ventas ? ($row['nombre_receptor'] ?? '') : ($row['nombre_emisor'] ?? ''),
                'gravado' => round((float) ($row['subtotal_gravado'] ?? 0), 2),
                'iva' => round($iva, 2),
                'retenciones' => round((float) ($row['retenciones'] ?? 0), 2),
            ];
        }, $slice);

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'documento', 'label' => 'Documento'],
                ['key' => 'tercero', 'label' => $ventas ? 'Cliente' : 'Proveedor'],
                ['key' => 'gravado', 'label' => 'Gravado', 'numeric' => true],
                ['key' => 'iva', 'label' => 'IVA', 'numeric' => true],
                ['key' => 'retenciones', 'label' => 'Retenciones', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => [
                'gravado' => round(array_sum(array_column($filas, 'gravado')), 2),
                'iva' => round(array_sum(array_column($filas, 'iva')), 2),
                'retenciones' => round(array_sum(array_column($filas, 'retenciones')), 2),
            ],
            'nota' => 'Vista simplificada. Descargue PDF o Excel para el formato fiscal completo.',
        ];
    }

    /** @return array<string, mixed> */
    private function previewLegacyFilas(int $anio, int $mes, int $limit, string $tipo): array
    {
        $legacy = app(LibrosIvaLegacyController::class);
        $req = $this->libroRequest($anio, $mes);
        $response = $tipo === 'compras' ? $legacy->compras($req) : $legacy->consumidores($req);
        $payload = $response->getData(true);
        $rows = is_array($payload['filas'] ?? null) ? $payload['filas'] : (is_array($payload) && array_is_list($payload) ? $payload : []);
        $total = count($rows);
        $slice = array_slice($rows, 0, $limit);
        $filas = array_map(fn (array $row) => [
            'fecha' => $row['fecha'] ?? ($row['Fecha'] ?? ''),
            'documento' => $row['num_documento'] ?? ($row['documento'] ?? ($row['No'] ?? '')),
            'tercero' => $row['nombre_proveedor'] ?? ($row['nombre_cliente'] ?? ($row['Proveedor'] ?? ($row['Cliente'] ?? ''))),
            'total' => round((float) ($row['total'] ?? ($row['Total'] ?? 0)), 2),
        ], $slice);

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'documento', 'label' => 'Documento'],
                ['key' => 'tercero', 'label' => 'Tercero'],
                ['key' => 'total', 'label' => 'Total', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => ['total' => round(array_sum(array_column($filas, 'total')), 2)],
        ];
    }

    /** @return array<string, mixed> */
    private function previewHdContribuyentes(int $anio, int $mes, int $limit): array
    {
        $payload = app(LibrosIvaHdController::class)
            ->contribuyentes($this->libroRequest($anio, $mes))
            ->getData(true);
        $rows = is_array($payload['filas'] ?? null) ? $payload['filas'] : [];
        $total = count($rows);
        $slice = array_slice($rows, 0, $limit);
        $filas = array_map(fn (array $row) => [
            'fecha' => $row['fecha'] ?? ($row['Fecha'] ?? ''),
            'documento' => $row['documento'] ?? ($row['No'] ?? ''),
            'tercero' => $row['cliente'] ?? ($row['Cliente'] ?? ''),
            'total' => round((float) ($row['total'] ?? ($row['Total'] ?? 0)), 2),
        ], $slice);

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha'],
                ['key' => 'documento', 'label' => 'Documento'],
                ['key' => 'tercero', 'label' => 'Cliente'],
                ['key' => 'total', 'label' => 'Total', 'numeric' => true],
            ],
            'filas' => $filas,
            'total' => $total,
            'mostrando' => count($filas),
            'totales' => ['total' => round(array_sum(array_column($filas, 'total')), 2)],
        ];
    }

    private function soportaPdf(string $clave, string $moduloPais): bool
    {
        if ($moduloPais === LibroIvaPaisResolver::TIPO_CR) {
            return in_array($clave, ['cr_ventas_detalle', 'cr_compras_detalle'], true);
        }

        return in_array($clave, ['compras_libro', 'ventas_contribuyentes_libro', 'ventas_consumidor_libro'], true);
    }

    private function soportaExcel(string $clave, string $moduloPais): bool
    {
        if ($moduloPais === LibroIvaPaisResolver::TIPO_CR) {
            return in_array($clave, ['cr_ventas_detalle', 'cr_compras_detalle', 'resumen_iva'], true);
        }

        return !in_array($clave, ['compras_anexo', 'ventas_contribuyentes_anexo', 'ventas_consumidor_anexo', 'partida_iva', 'retenciones_libro'], true);
    }

    private function soportaCsv(string $clave, string $moduloPais): bool
    {
        if ($moduloPais === LibroIvaPaisResolver::TIPO_CR) {
            return in_array($clave, ['cr_ventas_detalle', 'cr_compras_detalle'], true);
        }

        return str_ends_with($clave, '_anexo');
    }

    private function libroRequest(int $anio, int $mes, array $extra = []): BaseLibroIVARequest
    {
        [$inicio, $fin] = $this->rangoMes($anio, $mes);
        $payload = array_merge(['inicio' => $inicio, 'fin' => $fin], $extra);
        $sym = Request::create('/api/contadores/libros-iva', 'GET', $payload);
        /** @var BaseLibroIVARequest $form */
        $form = BaseLibroIVARequest::createFrom($sym);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form;
    }

    /** @return array{0: string, 1: string} */
    private function rangoMes(int $anio, int $mes): array
    {
        $inicio = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/El_Salvador')->startOfMonth()->toDateString();
        $fin = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/El_Salvador')->endOfMonth()->toDateString();

        return [$inicio, $fin];
    }
}
