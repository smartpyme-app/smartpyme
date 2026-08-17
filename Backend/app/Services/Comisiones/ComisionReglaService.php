<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

class ComisionReglaService
{
    /** @return EloquentCollection<int, ComisionRegla> */
    public function listar(int $idEmpresa, ?bool $activo = null): EloquentCollection
    {
        $query = ComisionRegla::query()
            ->where('id_empresa', $idEmpresa)
            ->orderBy('nombre');

        if ($activo !== null) {
            $query->where('activo', $activo);
        }

        return $query->get();
    }

    public function obtener(int $idEmpresa, int $id): ComisionRegla
    {
        return ComisionRegla::query()
            ->where('id_empresa', $idEmpresa)
            ->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function crear(int $idEmpresa, array $data): ComisionRegla
    {
        $payload = $this->prepararPayload($data);

        return ComisionRegla::query()->create([
            'id_empresa' => $idEmpresa,
            ...$payload,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function actualizar(int $idEmpresa, int $id, array $data): ComisionRegla
    {
        $regla = $this->obtener($idEmpresa, $id);
        $payload = $this->prepararPayload($data, $regla);
        $regla->update($payload);

        return $regla->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombre: string, tipo_calculo: string, alcance: string, id_vendedores: array<int>|null, momento_devengo: string, reemplaza_global: bool, config: array<string, mixed>, activo: bool}
     */
    public function prepararPayload(array $data, ?ComisionRegla $existing = null): array
    {
        $payload = $this->normalizarPayload($data, $existing);
        $this->validarConfig($payload['tipo_calculo'], $payload['config']);
        $this->validarAlcance($payload['alcance'], $payload['id_vendedores']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombre: string, tipo_calculo: string, alcance: string, id_vendedores: array<int>|null, momento_devengo: string, reemplaza_global: bool, config: array<string, mixed>, activo: bool}
     */
    private function normalizarPayload(array $data, ?ComisionRegla $existing = null): array
    {
        $tipo = (string) ($data['tipo_calculo'] ?? $existing?->tipo_calculo ?? '');
        $alcance = (string) ($data['alcance'] ?? $existing?->alcance ?? ComisionRegla::ALCANCE_GLOBAL);
        $momento = (string) ($data['momento_devengo'] ?? $existing?->momento_devengo ?? ComisionRegla::MOMENTO_AL_PAGAR);

        $tipos = [
            ComisionRegla::TIPO_POR_CATEGORIA,
            ComisionRegla::TIPO_POR_VOLUMEN,
            ComisionRegla::TIPO_POR_MARGEN,
        ];
        if (! in_array($tipo, $tipos, true)) {
            throw ValidationException::withMessages([
                'tipo_calculo' => ['El tipo de cálculo no es válido.'],
            ]);
        }

        $alcances = [
            ComisionRegla::ALCANCE_GLOBAL,
            ComisionRegla::ALCANCE_INDIVIDUAL,
            ComisionRegla::ALCANCE_EQUIPO,
        ];
        if (! in_array($alcance, $alcances, true)) {
            throw ValidationException::withMessages([
                'alcance' => ['El alcance debe ser global, individual o equipo.'],
            ]);
        }

        $momentos = [
            ComisionRegla::MOMENTO_AL_PAGAR,
            ComisionRegla::MOMENTO_AL_FACTURAR,
            ComisionRegla::MOMENTO_POR_ABONO,
        ];
        if (! in_array($momento, $momentos, true)) {
            throw ValidationException::withMessages([
                'momento_devengo' => ['El momento de devengo no es válido.'],
            ]);
        }

        $idVendedores = $data['id_vendedores'] ?? $existing?->id_vendedores ?? null;
        if ($alcance === ComisionRegla::ALCANCE_GLOBAL) {
            $idVendedores = null;
        } elseif (is_array($idVendedores)) {
            $idVendedores = array_values(array_unique(array_map('intval', $idVendedores)));
        }

        $config = (array) ($data['config'] ?? $existing?->config ?? []);
        if (array_key_exists('salario_base', $data)) {
            $config['salario_base'] = (float) $data['salario_base'];
        }

        return [
            'nombre' => (string) ($data['nombre'] ?? $existing?->nombre ?? ''),
            'tipo_calculo' => $tipo,
            'alcance' => $alcance,
            'id_vendedores' => $idVendedores,
            'momento_devengo' => $momento,
            'reemplaza_global' => (bool) ($data['reemplaza_global'] ?? $existing?->reemplaza_global ?? false),
            'config' => $config,
            'activo' => (bool) ($data['activo'] ?? $existing?->activo ?? true),
        ];
    }

    /** @param  array<string, mixed>  $config */
    private function validarConfig(string $tipo, array $config): void
    {
        if ($tipo === ComisionRegla::TIPO_POR_CATEGORIA) {
            return;
        }

        if ($tipo === ComisionRegla::TIPO_POR_MARGEN) {
            if (! $this->porcentajeValido($config['porcentaje'] ?? null)) {
                throw ValidationException::withMessages([
                    'config' => ['por_margen requiere porcentaje numérico entre 0 y 100.'],
                ]);
            }

            return;
        }

        if (empty($config['tramos']) || ! is_array($config['tramos'])) {
            throw ValidationException::withMessages([
                'config' => ['por_volumen requiere tramos en config.'],
            ]);
        }

        foreach ($config['tramos'] as $tramo) {
            if (! is_array($tramo) || ! $this->numeroNoNegativo($tramo['umbral'] ?? null) || ! $this->porcentajeValido($tramo['porcentaje'] ?? null)) {
                throw ValidationException::withMessages([
                    'config' => ['Cada tramo de volumen requiere umbral numérico y porcentaje entre 0 y 100.'],
                ]);
            }
        }
    }

    private function porcentajeValido(mixed $valor): bool
    {
        return is_numeric($valor) && (float) $valor >= 0 && (float) $valor <= 100;
    }

    private function numeroNoNegativo(mixed $valor): bool
    {
        return is_numeric($valor) && (float) $valor >= 0;
    }

    /** @param  array<int>|null  $idVendedores */
    private function validarAlcance(string $alcance, ?array $idVendedores): void
    {
        if ($alcance === ComisionRegla::ALCANCE_INDIVIDUAL && count($idVendedores ?? []) !== 1) {
            throw ValidationException::withMessages([
                'id_vendedores' => ['El alcance individual requiere exactamente un vendedor.'],
            ]);
        }

        if ($alcance === ComisionRegla::ALCANCE_EQUIPO && empty($idVendedores)) {
            throw ValidationException::withMessages([
                'id_vendedores' => ['Seleccione al menos un vendedor para este alcance.'],
            ]);
        }
    }
}
