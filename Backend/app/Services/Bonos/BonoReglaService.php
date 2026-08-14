<?php

namespace App\Services\Bonos;

use App\Models\Bonos\BonoRegla;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

class BonoReglaService
{
    /** @return EloquentCollection<int, BonoRegla> */
    public function listar(int $idEmpresa, ?bool $activo = null): EloquentCollection
    {
        $query = BonoRegla::query()
            ->where('id_empresa', $idEmpresa)
            ->orderBy('nombre');

        if ($activo !== null) {
            $query->where('activo', $activo);
        }

        return $query->get();
    }

    public function obtener(int $idEmpresa, int $id): BonoRegla
    {
        return BonoRegla::query()
            ->where('id_empresa', $idEmpresa)
            ->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function crear(int $idEmpresa, array $data): BonoRegla
    {
        $payload = $this->normalizarPayload($data);
        $this->validarConfig($payload['tipo'], $payload['config']);
        $this->validarAlcance($payload['alcance'], $payload['id_vendedores']);

        return BonoRegla::query()->create([
            'id_empresa' => $idEmpresa,
            ...$payload,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function actualizar(int $idEmpresa, int $id, array $data): BonoRegla
    {
        $regla = $this->obtener($idEmpresa, $id);
        $payload = $this->normalizarPayload($data, $regla);
        $this->validarConfig($payload['tipo'], $payload['config']);
        $this->validarAlcance($payload['alcance'], $payload['id_vendedores']);

        $regla->update($payload);

        return $regla->fresh();
    }

    public function eliminar(int $idEmpresa, int $id): BonoRegla
    {
        $regla = $this->obtener($idEmpresa, $id);
        $regla->update(['activo' => false]);

        return $regla->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombre: string, tipo: string, ventana: string, config: array<string, mixed>, activo: bool, alcance: string, id_vendedores: array<int>|null, reemplaza_global: bool}
     */
    private function normalizarPayload(array $data, ?BonoRegla $existing = null): array
    {
        $tipo = (string) ($data['tipo'] ?? $existing?->tipo ?? '');
        $ventana = (string) ($data['ventana'] ?? $existing?->ventana ?? BonoRegla::VENTANA_MENSUAL);
        $alcance = (string) ($data['alcance'] ?? $existing?->alcance ?? BonoRegla::ALCANCE_GLOBAL);

        $tipos = [
            BonoRegla::TIPO_META_FIJA,
            BonoRegla::TIPO_ESCALONADO,
            BonoRegla::TIPO_PORCENTAJE_EXCEDENTE,
            BonoRegla::TIPO_GRUPAL,
            BonoRegla::TIPO_CUALITATIVO_MANUAL,
        ];
        if (! in_array($tipo, $tipos, true)) {
            throw ValidationException::withMessages([
                'tipo' => ['El tipo de regla no es válido.'],
            ]);
        }

        $alcances = [
            BonoRegla::ALCANCE_GLOBAL,
            BonoRegla::ALCANCE_VENDEDORES,
            BonoRegla::ALCANCE_INDIVIDUAL,
            BonoRegla::ALCANCE_EQUIPO,
        ];
        if (! in_array($alcance, $alcances, true)) {
            throw ValidationException::withMessages([
                'alcance' => ['El alcance debe ser global, individual, equipo o vendedores.'],
            ]);
        }

        if ($tipo === BonoRegla::TIPO_GRUPAL && $alcance !== BonoRegla::ALCANCE_EQUIPO) {
            throw ValidationException::withMessages([
                'alcance' => ['El tipo grupal requiere alcance equipo.'],
            ]);
        }

        $idVendedores = $data['id_vendedores'] ?? $existing?->id_vendedores ?? null;
        if ($alcance === BonoRegla::ALCANCE_GLOBAL) {
            $idVendedores = null;
        } elseif (is_array($idVendedores)) {
            $idVendedores = array_values(array_unique(array_map('intval', $idVendedores)));
        }

        return [
            'nombre' => (string) ($data['nombre'] ?? $existing?->nombre ?? ''),
            'tipo' => $tipo,
            'ventana' => $ventana,
            'config' => (array) ($data['config'] ?? $existing?->config ?? []),
            'activo' => (bool) ($data['activo'] ?? $existing?->activo ?? true),
            'alcance' => $alcance,
            'id_vendedores' => $idVendedores,
            'reemplaza_global' => (bool) ($data['reemplaza_global'] ?? $existing?->reemplaza_global ?? false),
        ];
    }

    /** @param  array<string, mixed>  $config */
    private function validarConfig(string $tipo, array $config): void
    {
        if ($tipo === BonoRegla::TIPO_CUALITATIVO_MANUAL) {
            return;
        }

        if ($tipo === BonoRegla::TIPO_META_FIJA || $tipo === BonoRegla::TIPO_GRUPAL) {
            if (! isset($config['meta'], $config['bono'])) {
                throw ValidationException::withMessages([
                    'config' => ["{$tipo} requiere meta y bono en config."],
                ]);
            }

            return;
        }

        if ($tipo === BonoRegla::TIPO_PORCENTAJE_EXCEDENTE) {
            if (! isset($config['meta'], $config['porcentaje'])) {
                throw ValidationException::withMessages([
                    'config' => ['porcentaje_excedente requiere meta y porcentaje en config.'],
                ]);
            }

            return;
        }

        if (empty($config['tramos']) || ! is_array($config['tramos'])) {
            throw ValidationException::withMessages([
                'config' => ['escalonado requiere tramos en config.'],
            ]);
        }
    }

    /** @param  array<int>|null  $idVendedores */
    private function validarAlcance(string $alcance, ?array $idVendedores): void
    {
        if ($alcance === BonoRegla::ALCANCE_INDIVIDUAL && count($idVendedores ?? []) !== 1) {
            throw ValidationException::withMessages([
                'id_vendedores' => ['El alcance individual requiere exactamente un vendedor.'],
            ]);
        }

        if (in_array($alcance, [BonoRegla::ALCANCE_EQUIPO, BonoRegla::ALCANCE_VENDEDORES], true) && empty($idVendedores)) {
            throw ValidationException::withMessages([
                'id_vendedores' => ['Seleccione al menos un vendedor para este alcance.'],
            ]);
        }
    }
}
