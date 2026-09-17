<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Activo;
use App\Models\Contabilidad\ActivoDepreciacion;
use App\Models\Contabilidad\ActivoMovimiento;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BajaActivoService
{
    public function registrar(Activo $activo, array $data, int $usuarioId): Activo
    {
        return DB::transaction(function () use ($activo, $data, $usuarioId) {
            $tipo = $data['tipo'];

            if (in_array($tipo, ['desecho', 'venta'], true)) {
                return $this->registrarBajaContable($activo, $data, $usuarioId, $tipo);
            }

            return $this->registrarTransferencia($activo, $data, $usuarioId);
        });
    }

    private function registrarBajaContable(Activo $activo, array $data, int $usuarioId, string $tipo): Activo
    {
        if ($activo->estado_registro === 'baja') {
            throw new RuntimeException('El activo ya fue dado de baja.');
        }

        ActivoDepreciacion::withoutGlobalScopes()
            ->where('id_activo', $activo->id)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'cancelada']);

        $activo->estado = 'Desechado';
        $activo->estado_registro = 'baja';
        $activo->fecha_retiro = $data['fecha'];
        $activo->save();

        ActivoMovimiento::create([
            'id_activo' => $activo->id,
            'id_empresa' => $activo->id_empresa,
            'id_usuario' => $usuarioId,
            'tipo' => $tipo === 'venta' ? 'venta' : 'baja',
            'fecha' => $data['fecha'],
            'descripcion' => $data['motivo'] ?? null,
            'monto' => $tipo === 'venta' ? ($data['monto_venta'] ?? null) : null,
        ]);

        return $activo->fresh(['categoria', 'sucursal', 'responsable']);
    }

    private function registrarTransferencia(Activo $activo, array $data, int $usuarioId): Activo
    {
        if ($activo->estado_registro === 'baja') {
            throw new RuntimeException('No se puede transferir un activo dado de baja.');
        }

        $metadata = [];

        if (array_key_exists('id_sucursal', $data) && $data['id_sucursal'] !== null) {
            if ((int) $data['id_sucursal'] === (int) $activo->id_sucursal) {
                throw new RuntimeException('Indique una sucursal distinta a la actual.');
            }
            $metadata['id_sucursal_anterior'] = $activo->id_sucursal;
            $activo->id_sucursal = (int) $data['id_sucursal'];
        }

        if (array_key_exists('id_responsable', $data) && $data['id_responsable'] !== null) {
            if ((int) $data['id_responsable'] === (int) $activo->id_responsable) {
                throw new RuntimeException('Indique un responsable distinto al actual.');
            }
            $metadata['id_responsable_anterior'] = $activo->id_responsable;
            $activo->id_responsable = (int) $data['id_responsable'];
        }

        if (array_key_exists('ubicacion', $data) && $data['ubicacion'] !== null && $data['ubicacion'] !== '') {
            if ($data['ubicacion'] === $activo->ubicacion) {
                throw new RuntimeException('Indique una ubicación distinta a la actual.');
            }
            $metadata['ubicacion_anterior'] = $activo->ubicacion;
            $activo->ubicacion = $data['ubicacion'];
        }

        if ($metadata === []) {
            throw new RuntimeException('Debe cambiar sucursal, responsable o ubicación.');
        }

        $activo->save();

        ActivoMovimiento::create([
            'id_activo' => $activo->id,
            'id_empresa' => $activo->id_empresa,
            'id_usuario' => $usuarioId,
            'tipo' => 'transferencia',
            'fecha' => $data['fecha'],
            'descripcion' => $data['motivo'] ?? null,
            'metadata' => $metadata,
        ]);

        return $activo->fresh(['categoria', 'sucursal', 'responsable']);
    }
}
