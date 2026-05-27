<?php

namespace App\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use Illuminate\Support\Collection;

/**
 * Búsqueda y resolución de productos para importación de DTE / JSON en compras.
 * Centraliza la lógica fuera del controlador para reutilizarla (p. ej. varios JSON, jobs).
 */
class ProductoImportacionDteService
{
    /**
     * @param  array<int, array{codigo?: mixed, descripcion?: mixed, numItem?: mixed}>  $items
     * @return array<int, array{numItem: mixed, producto: Producto|null}>
     */
    public function resolverImportacionDte(int $idEmpresa, array $items): array
    {
        $codigosSet = [];
        foreach ($items as $it) {
            $c = isset($it['codigo']) ? trim((string) $it['codigo']) : '';
            if ($c !== '') {
                $codigosSet[$c] = true;
            }
        }
        $codigosLista = array_keys($codigosSet);

        $mapPorCodigo = [];
        if ($codigosLista !== []) {
            $productos = Producto::where('enable', true)
                ->where('id_empresa', $idEmpresa)
                ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
                ->whereHas('proveedores', function ($q) use ($codigosLista) {
                    $q->whereIn('cod_proveed_prod', $codigosLista);
                })
                ->with(['inventarios', 'lotes', 'precios', 'proveedores.proveedor'])
                ->orderBy('id')
                ->get();

            foreach ($productos as $p) {
                foreach ($p->proveedores as $pp) {
                    $cod = $pp->cod_proveed_prod;
                    if ($cod === null || $cod === '') {
                        continue;
                    }
                    if (isset($codigosSet[$cod]) && ! isset($mapPorCodigo[$cod])) {
                        $mapPorCodigo[$cod] = $p;
                    }
                }
            }

            // Costa Rica: CABYS en línea del XML o código interno de 13 dígitos.
            $productosCabys = Producto::where('enable', true)
                ->where('id_empresa', $idEmpresa)
                ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
                ->where(function ($q) use ($codigosLista) {
                    $q->whereIn('codigo_cabys', $codigosLista)
                        ->orWhereIn('codigo', $codigosLista);
                })
                ->with(['inventarios', 'lotes', 'precios'])
                ->orderBy('id')
                ->get();

            foreach ($productosCabys as $p) {
                foreach ([$p->codigo_cabys, $p->codigo] as $codProd) {
                    $codProd = $codProd !== null ? trim((string) $codProd) : '';
                    if ($codProd !== '' && isset($codigosSet[$codProd]) && ! isset($mapPorCodigo[$codProd])) {
                        $mapPorCodigo[$codProd] = $p;
                    }
                }
            }
        }

        $descripcionesUnicas = [];
        foreach ($items as $it) {
            $cod = isset($it['codigo']) ? trim((string) $it['codigo']) : '';
            if ($cod !== '' && isset($mapPorCodigo[$cod])) {
                continue;
            }
            $desc = isset($it['descripcion']) ? trim((string) $it['descripcion']) : '';
            if (strlen($desc) >= 2) {
                $descripcionesUnicas[$desc] = true;
            }
        }

        $mapPorDescripcion = [];
        foreach (array_keys($descripcionesUnicas) as $desc) {
            $coincidencias = $this->productosPorNombreFuzzy($idEmpresa, $desc, 5);
            if ($coincidencias->isNotEmpty()) {
                $mapPorDescripcion[$desc] = $coincidencias->first();
            }
        }

        $resultados = [];
        foreach ($items as $it) {
            $numItem = $it['numItem'] ?? null;
            $cod = isset($it['codigo']) ? trim((string) $it['codigo']) : '';
            $desc = isset($it['descripcion']) ? trim((string) $it['descripcion']) : '';

            $producto = null;
            if ($cod !== '' && isset($mapPorCodigo[$cod])) {
                $producto = $mapPorCodigo[$cod];
            } elseif (strlen($desc) >= 2 && isset($mapPorDescripcion[$desc])) {
                $producto = $mapPorDescripcion[$desc];
            } elseif ($cod === '' && strlen($desc) >= 2 && $this->esDescripcionOtrosCargos($desc)) {
                $producto = $this->productoServicioParaOtrosCargos($idEmpresa, $desc);
            }

            $resultados[] = [
                'numItem' => $numItem,
                'producto' => $producto,
            ];
        }

        return $resultados;
    }

    /**
     * @param  array<int, array{termino: string, palabras?: array<int, string>|null}>  $consultas
     * @return array<int, Collection<int, Producto>>
     */
    public function sugerenciasLote(int $idEmpresa, array $consultas, int $limite): array
    {
        $resultados = [];
        foreach ($consultas as $c) {
            $palabras = $c['palabras'] ?? [];
            $resultados[] = $this->productosSugerencias(
                $idEmpresa,
                $c['termino'],
                is_array($palabras) ? $palabras : [],
                $limite
            );
        }

        return $resultados;
    }

    /**
     * @return Collection<int, Producto>
     */
    public function productosPorNombreFuzzy(int $idEmpresa, string $nombre, int $limite = 5): Collection
    {
        $empresa = Empresa::find($idEmpresa);
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        return Producto::where('enable', true)
            ->where('id_empresa', $idEmpresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'precios'])
            ->where(function ($q) use ($nombre, $incluirComponenteQuimico) {
                $q->where('nombre', 'like', "%{$nombre}%");
                if ($incluirComponenteQuimico) {
                    $q->orWhere('componente_quimico', 'like', "%{$nombre}%");
                }
            })
            ->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();
    }

    /**
     * @param  array<int, string>  $palabras
     * @return Collection<int, Producto>
     */
    public function productosSugerencias(int $idEmpresa, string $termino, array $palabras, int $limite): Collection
    {
        $empresa = Empresa::find($idEmpresa);
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        $query = Producto::where('enable', true)
            ->where('id_empresa', $idEmpresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'lotes', 'precios']);

        $query->where(function ($q) use ($termino, $incluirComponenteQuimico) {
            $q->where('nombre', 'like', "%$termino%")
                ->orWhere('codigo', 'like', "%$termino%")
                ->orWhere('barcode', 'like', "%$termino%")
                ->orWhere('etiquetas', 'like', "%$termino%")
                ->orWhere('marca', 'like', "%$termino%")
                ->orWhere('descripcion', 'like', "%$termino%");
            if ($incluirComponenteQuimico) {
                $q->orWhere('componente_quimico', 'like', "%$termino%");
            }
        });

        if (! empty($palabras)) {
            $query->orWhere(function ($q) use ($palabras, $incluirComponenteQuimico) {
                foreach ($palabras as $palabra) {
                    if (strlen($palabra) > 2) {
                        $q->orWhere('nombre', 'like', "%$palabra%")
                            ->orWhere('descripcion', 'like', "%$palabra%")
                            ->orWhere('etiquetas', 'like', "%$palabra%");
                        if ($incluirComponenteQuimico) {
                            $q->orWhere('componente_quimico', 'like', "%$palabra%");
                        }
                    }
                }
            });
        }

        return $query->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();
    }

    private function esDescripcionOtrosCargos(string $descripcion): bool
    {
        $d = mb_strtolower($descripcion);

        return str_contains($d, 'propina')
            || str_contains($d, 'servicio')
            || str_contains($d, 'otros cargo')
            || str_contains($d, 'impuesto de servicio');
    }

    private function productoServicioParaOtrosCargos(int $idEmpresa, string $descripcion): ?Producto
    {
        $termino = '%'.$descripcion.'%';

        $porNombre = Producto::where('enable', true)
            ->where('id_empresa', $idEmpresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->where(function ($q) use ($termino) {
                $q->where('nombre', 'like', $termino)
                    ->orWhere('descripcion', 'like', $termino);
            })
            ->orderBy('nombre')
            ->first();

        if ($porNombre) {
            return $porNombre;
        }

        return Producto::where('enable', true)
            ->where('id_empresa', $idEmpresa)
            ->where('tipo', 'Servicio')
            ->orderBy('nombre')
            ->first();
    }
}
