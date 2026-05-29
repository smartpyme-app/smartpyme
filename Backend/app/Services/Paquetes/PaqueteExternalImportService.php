<?php

namespace App\Services\Paquetes;

use App\Models\Inventario\Paquete;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Sucursal;
use Illuminate\Support\Facades\Validator;

class PaqueteExternalImportService
{
    /**
     * Reglas de validación por fila (misma lógica que import Excel).
     *
     * @return array<string, mixed>
     */
    public static function rowValidationRules(): array
    {
        return [
            'cliente' => 'required|string',
            'codigo_asesor' => 'required',
            'wr' => 'required',
            'guia' => 'required',
            'piezas' => 'required|numeric',
            'precio' => 'required|numeric',
            'peso' => 'required|numeric',
            'otros' => 'required|numeric',
            'cuenta_a_terceros' => 'required|numeric',
            'embalaje' => 'required',
            'total' => 'required|numeric',
            'transportista' => 'nullable|string',
            'consignatario' => 'nullable|string',
            'transportador' => 'nullable|string',
            'seguimiento' => 'nullable|string',
            'volumen' => 'nullable|numeric',
            'nota' => 'nullable|string',
        ];
    }

    /**
     * Resuelve id_sucursal: prioridad a id_sucursal; si no, nombre (trim, case-insensitive).
     *
     * @return array{ok: bool, id: int|null, error: string|null}
     */
    public function resolveSucursalId(int $empresaId, ?int $idSucursal, ?string $nombreSucursal): array
    {
        if ($idSucursal !== null && $idSucursal > 0) {
            $s = Sucursal::withoutGlobalScopes()
                ->where('id_empresa', $empresaId)
                ->where('id', $idSucursal)
                ->first();

            if (!$s) {
                return ['ok' => false, 'id' => null, 'error' => 'La sucursal indicada no existe o no pertenece a la empresa.'];
            }
            if (!$this->sucursalEstaActiva($s)) {
                return ['ok' => false, 'id' => null, 'error' => 'La sucursal está inactiva.'];
            }

            return ['ok' => true, 'id' => (int) $s->id, 'error' => null];
        }

        $nombre = $nombreSucursal !== null ? trim($nombreSucursal) : '';
        if ($nombre === '') {
            return ['ok' => false, 'id' => null, 'error' => 'Debe enviar id_sucursal o sucursal (nombre).'];
        }

        $normalized = mb_strtolower($nombre, 'UTF-8');
        $candidatos = Sucursal::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->get()
            ->filter(function ($s) use ($normalized) {
                return mb_strtolower(trim((string) $s->nombre), 'UTF-8') === $normalized;
            })
            ->values();

        if ($candidatos->isEmpty()) {
            return ['ok' => false, 'id' => null, 'error' => "No se encontró sucursal con nombre '{$nombre}'."];
        }
        if ($candidatos->count() > 1) {
            return ['ok' => false, 'id' => null, 'error' => "Nombre de sucursal ambíguo: '{$nombre}'. Use id_sucursal."];
        }

        $s = $candidatos->first();
        if (!$this->sucursalEstaActiva($s)) {
            return ['ok' => false, 'id' => null, 'error' => 'La sucursal está inactiva.'];
        }

        return ['ok' => true, 'id' => (int) $s->id, 'error' => null];
    }

    private function sucursalEstaActiva(Sucursal $s): bool
    {
        $a = $s->activo;

        return in_array((string) $a, ['1', 'true', 'Si', 'SI', 'si'], true)
            || $a === true
            || $a === 1;
    }

    /**
     * Usuario bajo el cual se registran paquetes/clientes creados (API sin JWT).
     * Preferencia: Administrador activo, luego Supervisor, luego cualquier usuario activo.
     */
    public function resolveSystemUserIdForEmpresa(int $empresaId): ?int
    {
        $activos = [1, '1', true];

        $admin = User::query()
            ->where('id_empresa', $empresaId)
            ->where('tipo', 'Administrador')
            ->whereIn('enable', $activos)
            ->orderBy('id')
            ->first();

        if ($admin) {
            return (int) $admin->id;
        }

        $supervisor = User::query()
            ->where('id_empresa', $empresaId)
            ->where('tipo', 'Supervisor')
            ->whereIn('enable', $activos)
            ->orderBy('id')
            ->first();

        if ($supervisor) {
            return (int) $supervisor->id;
        }

        $any = User::query()
            ->where('id_empresa', $empresaId)
            ->whereIn('enable', $activos)
            ->orderBy('id')
            ->first();

        return $any ? (int) $any->id : null;
    }

    /**
     * Crea paquete si no existe wr en la empresa (misma regla que Excel).
     *
     * @param  array<string, mixed>  $row  Claves como en Excel (cliente, wr, guia, …)
     * @return array{status: string, message: ?string, paquete: ?Paquete}
     */
    public function importRow(int $empresaId, int $idUsuario, int $idSucursal, array $row): array
    {
        $validator = Validator::make($row, self::rowValidationRules());
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'paquete' => null,
            ];
        }

        $idCliente = Cliente::withoutGlobalScopes()
            ->where('nombre', $row['cliente'])
            ->where('id_empresa', $empresaId)
            ->value('id');

        if (!$idCliente) {
            $cliente = new Cliente();
            $cliente->nombre = $row['cliente'];
            $cliente->enable = true;
            $cliente->id_usuario = $idUsuario;
            $cliente->id_empresa = $empresaId;
            $cliente->save();
            $idCliente = $cliente->id;
        }

        $paqueteExistente = Paquete::withoutGlobalScopes()
            ->where('wr', $row['wr'])
            ->where('id_empresa', $empresaId)
            ->first();

        if ($paqueteExistente) {
            return [
                'status' => 'skipped',
                'message' => 'Ya existe un paquete con este WR.',
                'paquete' => $paqueteExistente,
            ];
        }

        $idAsesor = null;
        if (!empty($row['codigo_asesor'])) {
            $idAsesor = User::query()
                ->where('id_empresa', $empresaId)
                ->whereIn('enable', [1, '1', true])
                ->where('codigo', trim((string) $row['codigo_asesor']))
                ->value('id');
        }

        $paquete = new Paquete();
        $paquete->fecha = date('Y-m-d');
        $paquete->wr = $row['wr'];
        $paquete->transportista = $row['transportista'] ?? '';
        $paquete->consignatario = $row['consignatario'] ?? '';
        $paquete->transportador = $row['transportador'] ?? '';
        $paquete->estado = 'En bodega';
        $paquete->num_seguimiento = $row['seguimiento'] ?? '';
        $paquete->num_guia = $row['guia'];
        $paquete->piezas = $row['piezas'];
        $paquete->embalaje = $row['embalaje'];
        $paquete->peso = $row['peso'];
        $paquete->precio = $row['precio'];
        $paquete->volumen = $row['volumen'] ?? null;
        $paquete->nota = $row['nota'] ?? '';
        $paquete->cuenta_a_terceros = $row['cuenta_a_terceros'];
        $paquete->otros = $row['otros'];
        $paquete->total = $row['total'];
        $paquete->id_cliente = $idCliente;
        $paquete->id_asesor = $idAsesor;
        $paquete->id_usuario = $idUsuario;
        $paquete->id_sucursal = $idSucursal;
        $paquete->id_empresa = $empresaId;
        $paquete->save();

        return [
            'status' => 'created',
            'message' => null,
            'paquete' => $paquete,
        ];
    }
}
