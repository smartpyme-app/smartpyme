<?php

namespace App\Services\Contadores;

use App\Models\Admin\Empresa;
use App\Models\Contadores\ContadorEmpresaDocumento;
use App\Models\Contadores\ContadorObligacionFiscal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ContadorCumplimientoService
{
    public const SLUG_LIBRE = 'libre';

    /** @var array<string, array{nombre: string, subible: bool}> */
    public const CATALOGO = [
        'escritura' => ['nombre' => 'Escritura de constitución', 'subible' => true],
        'nit_iva' => ['nombre' => 'NIT y tarjeta de IVA', 'subible' => true],
        'acuerdo_contador' => ['nombre' => 'Acuerdo de servicios con el contador', 'subible' => false],
        'poder' => ['nombre' => 'Poder especial / representación legal', 'subible' => true],
        'eeff' => ['nombre' => 'Últimos estados financieros', 'subible' => true],
        'libros' => ['nombre' => 'Libros contables legalizados', 'subible' => true],
        'matricula_local' => ['nombre' => 'Matrícula de comercio (local)', 'subible' => true],
        'permiso_municipal' => ['nombre' => 'Permiso municipal de operación', 'subible' => true],
    ];

    public function __construct(
        private ContadorCarteraMetricasService $metricas
    ) {}

    /**
     * @param  list<string>|null  $permisos
     * @return array<string, mixed>
     */
    public function vista(int $idEmpresa, int $anio, int $mes, ?array $permisos = null): array
    {
        $empresa = Empresa::query()->select(['id', 'nombre', 'logo', 'giro', 'nit'])->findOrFail($idEmpresa);
        $detalle = $this->metricas->detalleEmpresa($idEmpresa, $anio, $mes);

        Carbon::setLocale('es');
        $mesNombre = ucfirst(Carbon::create($anio, $mes, 1)->translatedFormat('F'));
        $vencimiento = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/El_Salvador')
            ->addMonth()
            ->day(14)
            ->startOfDay();

        $f07 = $detalle['impuestos']['f07'];
        $f14 = $detalle['impuestos']['f14'];

        $presentados = ContadorObligacionFiscal::query()
            ->where('id_empresa', $idEmpresa)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->pluck('codigo')
            ->all();

        $calendario = [
            $this->itemCalendario(
                'F-07',
                "IVA de {$mesNombre}",
                $vencimiento,
                (float) $f07['iva_a_pagar'],
                in_array('F-07', $presentados, true) ? 'presentado' : 'pendiente'
            ),
            $this->itemCalendario(
                'F-14',
                "Pago a cuenta y retenciones de {$mesNombre}",
                $vencimiento,
                (float) $f14['total'],
                in_array('F-14', $presentados, true) ? 'presentado' : 'pendiente'
            ),
        ];

        $documentos = $this->documentosEmpresa($empresa);
        $kpisDocs = $this->kpisDocumentos($documentos, $vencimiento);

        $permisos = $permisos ?? ContadorEmpresaAccesoPermisos::porDefecto();
        $puedeRegistrar = ContadorEmpresaAccesoPermisos::tiene($permisos, 'registrar');
        $puedeAprobar = ContadorEmpresaAccesoPermisos::tiene($permisos, 'aprobar');

        return [
            'empresa' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'logo' => $empresa->logo,
                'giro' => $empresa->giro,
                'nit' => $empresa->nit,
            ],
            'periodo' => ['mes' => $mes, 'anio' => $anio, 'label' => "{$mesNombre} {$anio}"],
            'permisos' => array_values($permisos),
            'kpis' => [
                'documentos' => $kpisDocs,
                'fiscal' => [
                    'iva_a_pagar' => (float) $f07['iva_a_pagar'],
                    'total_f14' => (float) $f14['total'],
                ],
            ],
            'calendario_tributario' => $calendario,
            'renovaciones' => $this->renovacionesAnuales($anio),
            'documentos' => $documentos,
            'capacidades' => [
                'subir_archivos' => $puedeRegistrar,
                'marcar_presentado' => $puedeAprobar,
                'recordatorios_email' => false,
                'logo_empresa' => false,
            ],
        ];
    }

    public function guardarDocumento(
        int $idEmpresa,
        UploadedFile $file,
        User $user,
        ?int $idDocumento = null,
        ?string $slug = null,
        ?string $venceEn = null,
        ?string $titulo = null,
    ): int {
        $slug = trim((string) ($slug ?? ''));
        if ($slug === '') {
            $slug = self::SLUG_LIBRE;
        }

        if ($slug !== self::SLUG_LIBRE) {
            if (!isset(self::CATALOGO[$slug]) || !self::CATALOGO[$slug]['subible']) {
                throw new \InvalidArgumentException('Tipo de documento no válido para carga.', 422);
            }
        }

        $tituloNorm = $titulo !== null ? trim($titulo) : null;
        if ($slug === self::SLUG_LIBRE && ($tituloNorm === null || $tituloNorm === '')) {
            throw new \InvalidArgumentException('Indique un nombre para el documento.', 422);
        }

        $ruta = $file->store("contadores/documentos/{$idEmpresa}");
        $attrs = [
            'ruta' => $ruta,
            'nombre_archivo' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'tamano_bytes' => $file->getSize(),
            'id_usuario_carga' => $user->id,
            'slug' => $slug,
        ];
        if ($venceEn !== null) {
            $attrs['vence_en'] = $venceEn !== '' ? $venceEn : null;
        }
        if ($tituloNorm !== null) {
            $attrs['titulo'] = $tituloNorm !== '' ? $tituloNorm : null;
        } elseif ($slug !== self::SLUG_LIBRE) {
            $attrs['titulo'] = self::CATALOGO[$slug]['nombre'];
        }

        if ($idDocumento) {
            $row = ContadorEmpresaDocumento::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id', $idDocumento)
                ->first();
            if (!$row) {
                throw new \InvalidArgumentException('Documento no encontrado.', 404);
            }
            if ($row->ruta) {
                Storage::delete($row->ruta);
            }
            $row->fill($attrs);
            $row->save();

            return (int) $row->id;
        }

        $row = ContadorEmpresaDocumento::query()->create(array_merge(
            ['id_empresa' => $idEmpresa],
            $attrs
        ));

        return (int) $row->id;
    }

    public function marcarPresentado(int $idEmpresa, string $codigo, int $mes, int $anio, User $user): void
    {
        $codigo = strtoupper(trim($codigo));
        if (!in_array($codigo, ['F-07', 'F-14'], true)) {
            throw new \InvalidArgumentException('Obligación fiscal no válida.', 422);
        }

        ContadorObligacionFiscal::query()->updateOrCreate(
            [
                'id_empresa' => $idEmpresa,
                'codigo' => $codigo,
                'mes' => $mes,
                'anio' => $anio,
            ],
            [
                'presentado_en' => now('America/El_Salvador'),
                'id_usuario_presento' => $user->id,
            ]
        );
    }

    /** @return list<array<string, mixed>> */
    private function documentosEmpresa(Empresa $empresa): array
    {
        $archivos = ContadorEmpresaDocumento::query()
            ->where('id_empresa', $empresa->id)
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (ContadorEmpresaDocumento $r) => $r->slug ?? self::SLUG_LIBRE);

        $nit = trim((string) ($empresa->nit ?? ''));
        $out = [];

        foreach (self::CATALOGO as $slug => $meta) {
            $rows = $archivos->get($slug, collect());
            if ($rows->isEmpty()) {
                $out[] = $this->filaCatalogoVacia($slug, $meta, $nit);
                continue;
            }
            $i = 0;
            foreach ($rows as $row) {
                $mapped = $this->mapDocumentoFila($row);
                $mapped['es_anexo'] = $i > 0;
                $mapped['slug'] = $slug;
                $out[] = $mapped;
                $i++;
            }
        }

        foreach ($archivos->get(self::SLUG_LIBRE, collect()) as $row) {
            $mapped = $this->mapDocumentoFila($row);
            $mapped['es_anexo'] = false;
            $mapped['es_otro'] = true;
            $out[] = $mapped;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function filaCatalogoVacia(string $slug, array $meta, string $nit): array
    {
        $estado = 'sin_cargar';
        $detalle = null;

        if ($slug === 'nit_iva' && $nit !== '') {
            $detalle = "NIT {$nit} en ficha · adjunte tarjeta de IVA";
        } elseif ($slug === 'acuerdo_contador') {
            $estado = 'vigente';
            $detalle = 'Acceso activo en portal contadores';
        } elseif (in_array($slug, ['eeff', 'matricula_local'], true)) {
            $estado = 'por_vencer';
            $detalle = 'Sin archivo · revisar vencimiento anual';
        }

        return [
            'id' => null,
            'slug' => $slug,
            'nombre' => $meta['nombre'],
            'nombre_catalogo' => $meta['nombre'],
            'subible' => $meta['subible'],
            'estado' => $estado,
            'detalle' => $detalle,
            'archivo_url' => null,
            'vence_en' => null,
            'es_anexo' => false,
            'es_otro' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function mapDocumentoFila(ContadorEmpresaDocumento $row): array
    {
        $hoy = Carbon::now('America/El_Salvador')->startOfDay();
        $slug = $row->slug ?? self::SLUG_LIBRE;
        $meta = self::CATALOGO[$slug] ?? null;
        $nombreCatalogo = $meta['nombre'] ?? null;
        $tituloFila = trim((string) ($row->titulo ?? ''));
        $nombreMostrar = $tituloFila !== ''
            ? $tituloFila
            : ($nombreCatalogo ?? ($row->nombre_archivo ?: 'Documento'));

        $detalle = 'Cargado ' . Carbon::parse($row->updated_at)->locale('es')->translatedFormat('d M Y');
        $estado = 'vigente';
        if ($row->vence_en && Carbon::parse($row->vence_en)->lte($hoy->copy()->addDays(45))) {
            $estado = 'por_vencer';
            $detalle .= ' · Vence ' . Carbon::parse($row->vence_en)->translatedFormat('d M Y');
        }

        return [
            'id' => $row->id,
            'slug' => $slug === self::SLUG_LIBRE ? null : $slug,
            'nombre' => $nombreMostrar,
            'nombre_catalogo' => $nombreCatalogo,
            'subible' => true,
            'estado' => $estado,
            'detalle' => $detalle,
            'archivo_url' => $row->ruta,
            'vence_en' => $row->vence_en?->toDateString(),
            'es_anexo' => false,
            'es_otro' => $slug === self::SLUG_LIBRE,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $documentos
     * @return array{al_dia: int, total: int, por_vencer: int, sin_cargar: int, proximo_vencimiento: array|null}
     */
    private function kpisDocumentos(array $documentos, Carbon $vencimientoFiscal): array
    {
        $slots = array_values(array_filter(
            $documentos,
            fn ($d) => empty($d['es_anexo']) && empty($d['es_otro'])
        ));
        $total = count($slots);
        $porVencer = count(array_filter($slots, fn ($d) => ($d['estado'] ?? '') === 'por_vencer'));
        $sinCargar = count(array_filter($slots, fn ($d) => ($d['estado'] ?? '') === 'sin_cargar'));
        $alDia = max(0, $total - $porVencer - $sinCargar);

        $hoy = Carbon::now('America/El_Salvador')->startOfDay();
        $proxima = $vencimientoFiscal->copy();
        foreach ($documentos as $doc) {
            if (empty($doc['vence_en'])) {
                continue;
            }
            $v = Carbon::parse($doc['vence_en'])->startOfDay();
            if ($v->gte($hoy) && $v->lt($proxima)) {
                $proxima = $v;
            }
        }
        $dias = (int) $hoy->diffInDays($proxima, false);

        return [
            'al_dia' => $alDia,
            'total' => $total,
            'por_vencer' => $porVencer,
            'sin_cargar' => $sinCargar,
            'proximo_vencimiento' => [
                'fecha' => $proxima->toDateString(),
                'label' => $proxima->translatedFormat('d M'),
                'dias_restantes' => $dias,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function renovacionesAnuales(int $anio): array
    {
        $vence = Carbon::create($anio, 12, 31, 0, 0, 0, 'America/El_Salvador');
        $hoy = Carbon::now('America/El_Salvador')->startOfDay();
        $dias = (int) $hoy->diffInDays($vence, false);
        $estado = $dias <= 90 ? 'vence_pronto' : 'vigente';

        return [
            [
                'slug' => 'matricula_empresa',
                'nombre' => 'Matrícula de comercio · empresa',
                'fecha_limite' => $vence->toDateString(),
                'monto_estimado' => null,
                'nota' => 'Monto estimado pendiente de balance general',
                'estado' => $estado,
            ],
            [
                'slug' => 'matricula_establecimiento',
                'nombre' => 'Matrícula de comercio · establecimiento',
                'fecha_limite' => $vence->toDateString(),
                'monto_estimado' => null,
                'nota' => 'Monto estimado pendiente de balance general',
                'estado' => $estado,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function itemCalendario(string $codigo, string $titulo, Carbon $fecha, float $monto, string $estado): array
    {
        return [
            'codigo' => $codigo,
            'titulo' => $titulo,
            'fecha' => $fecha->toDateString(),
            'fecha_corta' => strtoupper($fecha->translatedFormat('d M')),
            'monto' => round($monto, 2),
            'estado' => $estado,
        ];
    }
}
