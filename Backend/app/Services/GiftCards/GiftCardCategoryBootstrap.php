<?php

namespace App\Services\GiftCards;

use App\Models\Admin\Empresa;
use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\Admin\Funcionalidad;
use App\Models\Comisiones\ComisionCategoriaConfig;
use App\Models\Inventario\Categorias\Categoria;
use App\Services\Funcionalidades\FuncionalidadAccess;

class GiftCardCategoryBootstrap
{
    public const SLUG = 'gift-cards';

    private const SLUG_COMISIONES = 'comisiones-vendedores';

    private const CATEGORIA_NOMBRE = 'Gift Cards';

    public function ensureForEmpresa(Empresa $empresa): int
    {
        $categoria = Categoria::withoutGlobalScope('empresa')->firstOrCreate(
            [
                'nombre' => self::CATEGORIA_NOMBRE,
                'id_empresa' => $empresa->id,
            ],
            [
                'descripcion' => 'Productos gift card',
                'enable' => '1',
            ]
        );

        $this->persistConfiguracion($empresa->id, (int) $categoria->id);

        if (FuncionalidadAccess::empresaTieneSlug($empresa->id, self::SLUG_COMISIONES)) {
            ComisionCategoriaConfig::withoutGlobalScope('empresa')->updateOrCreate(
                [
                    'id_empresa' => $empresa->id,
                    'id_categoria' => $categoria->id,
                ],
                ['porcentaje' => 0]
            );
        }

        return (int) $categoria->id;
    }

    private function persistConfiguracion(int $idEmpresa, int $idCategoria): void
    {
        $funcionalidad = Funcionalidad::query()->where('slug', self::SLUG)->firstOrFail();

        $empresaFunc = EmpresaFuncionalidad::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_funcionalidad', $funcionalidad->id)
            ->first();

        if ($empresaFunc === null) {
            return;
        }

        $config = $empresaFunc->configuracion ?? [];
        $config['id_categoria_gift_cards'] = $idCategoria;
        $empresaFunc->configuracion = $config;
        $empresaFunc->save();
    }
}
