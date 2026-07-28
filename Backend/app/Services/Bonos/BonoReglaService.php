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
     * @return array{nombre: string, tipo: string, ventana: string, config: array<string, mixed>, activo: bool, alcance: string, id_vendedores: array<int>|null}
     */
    private function normalizarPayload(array $data, ?BonoRegla $existing = null): array
    {
        $tipo = (string) ($data['tipo'] ?? $existing?->tipo ?? '');
        $ventana = (string) ($data['ventana'] ?? $existing?->ventana ?? BonoRegla::VENTANA_MENSUAL);
        $alcance = (string) ($data['alcance'] ?? $existing?->alcance ?? BonoRegla::ALCANCE_GLOBAL);

        if (! in_array($tipo, [BonoRegla::TIPO_META_FIJA, BonoRegla::TIPO_ESCALONADO], true)) {
            throw ValidationException::withMessages([
                'tipo' => ['El tipo de regla no es válido.'],
            ]);
        }

        if (! in_array($alcance, [BonoRegla::ALCANCE_GLOBAL, BonoRegla::ALCANCE_VENDEDORES], true)) {
            throw ValidationException::withMessages([
                'alcance' => ['El alcance debe ser global o vendedores.'],
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
        ];
    }

    /** @param  array<string, mixed>  $config */
    private function validarConfig(string $tipo, array $config): void
    {
        if ($tipo === BonoRegla::TIPO_META_FIJA) {
            if (! isset($config['meta'], $config['bono'])) {
                throw ValidationException::withMessages([
                    'config' => ['meta_fija requiere meta y bono en config.'],
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
        if ($alcance === BonoRegla::ALCANCE_VENDEDORES && empty($idVendedores)) {
            throw ValidationException::withMessages([
                'id_vendedores' => ['Seleccione al menos un vendedor para reglas por vendedor.'],
            ]);
        }
    }
}
