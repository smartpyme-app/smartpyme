<?php

namespace App\Services\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Profesional;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfesionalService
{
    public function listar(int $idEmpresa, ?string $estado, ?int $idSucursal, bool $soloDisponibles): array
    {
        $consulta = Profesional::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa);

        if ($soloDisponibles || $estado === '1' || $estado === 'activo') {
            $consulta->where('activo', true);
        } elseif ($estado === '0' || $estado === 'inactivo') {
            $consulta->where('activo', false);
        }

        if ($idSucursal) {
            $consulta->whereExists(function ($q) use ($idSucursal) {
                $q->select(DB::raw(1))
                    ->from('clinica_profesional_sucursales')
                    ->whereColumn('clinica_profesional_sucursales.id_profesional', 'clinica_profesionales.id')
                    ->where('clinica_profesional_sucursales.id_sucursal', $idSucursal);
            });
        }

        return $consulta->orderByDesc('id')->get()->map(function (Profesional $profesional) use ($soloDisponibles) {
            return $this->presentar($profesional, ! $soloDisponibles);
        })->all();
    }

    public function ver(Profesional $profesional): array
    {
        return $this->presentar($profesional, true);
    }

    public function guardar(int $idEmpresa, array $datos): Profesional
    {
        $idUsuario = (int) ($datos['id_usuario'] ?? 0);
        $usuario = User::withoutGlobalScopes()->where('id', $idUsuario)->first(['id', 'id_empresa']);
        if ($usuario === null || (int) $usuario->id_empresa !== $idEmpresa) {
            throw ValidationException::withMessages([
                'id_usuario' => 'El usuario no pertenece a la empresa.',
            ]);
        }

        $sucursales = $this->sucursalesDeLaEmpresa($idEmpresa, $datos['sucursales'] ?? []);

        return DB::transaction(function () use ($idEmpresa, $idUsuario, $datos, $sucursales) {
            $profesional = Profesional::withoutGlobalScopes()->firstOrNew([
                'id_usuario' => $idUsuario,
            ]);
            if ($profesional->exists && (int) $profesional->id_empresa !== $idEmpresa) {
                throw ValidationException::withMessages([
                    'id_usuario' => 'El usuario no pertenece a la empresa.',
                ]);
            }

            $profesional->fill([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'cargo' => PacienteReglas::vacio($datos['cargo'] ?? null),
                'especialidad' => PacienteReglas::vacio($datos['especialidad'] ?? null),
                'colegiatura' => PacienteReglas::vacio($datos['colegiatura'] ?? null),
                'activo' => true,
            ]);
            $profesional->save();
            $profesional->sucursales()->sync($sucursales);

            return $profesional->fresh();
        });
    }

    public function desactivar(Profesional $profesional): Profesional
    {
        $profesional->update(['activo' => false]);

        return $profesional->fresh();
    }

    public function candidatos(int $idEmpresa): array
    {
        return User::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
            ])
            ->all();
    }

    private function presentar(Profesional $profesional, bool $conColegiatura): array
    {
        $usuario = User::withoutGlobalScopes()->where('id', $profesional->id_usuario)->first(['id', 'name']);
        $sucursales = DB::table('clinica_profesional_sucursales as ps')
            ->join('sucursales as s', 's.id', '=', 'ps.id_sucursal')
            ->where('ps.id_profesional', $profesional->id)
            ->orderBy('s.nombre')
            ->get(['s.id', 's.nombre']);
        $dato = [
            'id' => $profesional->id,
            'id_usuario' => $profesional->id_usuario,
            'nombre' => $usuario?->name,
            'cargo' => $profesional->cargo,
            'especialidad' => $profesional->especialidad,
            'activo' => (bool) $profesional->activo,
            'sucursales' => $sucursales->map(fn ($sucursal) => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ])->values()->all(),
        ];
        if ($conColegiatura) {
            $dato['colegiatura'] = $profesional->colegiatura;
        }

        return $dato;
    }

    private function sucursalesDeLaEmpresa(int $idEmpresa, mixed $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'sucursales' => 'Indica al menos una sucursal.',
            ]);
        }

        $validas = Sucursal::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validas) !== count($ids)) {
            throw ValidationException::withMessages([
                'sucursales' => 'Hay sucursales que no pertenecen a la empresa.',
            ]);
        }

        return $validas;
    }
}
