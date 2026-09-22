<?php

namespace App\Support\PrestamosEmpresa;

use App\Models\Compras\Proveedores\Proveedor;
use InvalidArgumentException;

final class AcreedorProveedorResolver
{
    /**
     * @return array{id_proveedor: int, acreedor: string, tipo_acreedor: string}
     */
    public static function resolve(int $idProveedor, int $empresaId): array
    {
        $proveedor = Proveedor::query()
            ->where('id_empresa', $empresaId)
            ->find($idProveedor);

        if (!$proveedor) {
            throw new InvalidArgumentException('Proveedor no encontrado.');
        }

        return self::fromProveedor($proveedor);
    }

    /**
     * @return array{id_proveedor: int, acreedor: string, tipo_acreedor: string}
     */
    public static function fromProveedor(Proveedor $proveedor): array
    {
        $nombre = $proveedor->tipo === 'Empresa'
            ? ($proveedor->nombre_empresa ?: trim(($proveedor->nombre ?? '').' '.($proveedor->apellido ?? '')))
            : ($proveedor->nombre_completo ?: trim(($proveedor->nombre ?? '').' '.($proveedor->apellido ?? '')));

        if ($nombre === '') {
            throw new InvalidArgumentException('El proveedor no tiene nombre.');
        }

        return [
            'id_proveedor' => (int) $proveedor->id,
            'acreedor' => $nombre,
            'tipo_acreedor' => $proveedor->tipo === 'Empresa' ? 'institucion' : 'persona',
        ];
    }
}
