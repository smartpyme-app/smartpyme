<?php

namespace App\Services\Clinica;

use App\Models\Clinica\Paciente;
use App\Models\Clinica\PacienteResponsable;
use App\Models\Clinica\Responsable;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponsableService
{
    public function deFicha(Paciente $paciente): array
    {
        $vinculos = PacienteResponsable::query()
            ->where('id_paciente', $paciente->id)
            ->orderByDesc('es_principal')
            ->orderByDesc('id')
            ->get();

        return $vinculos->map(fn (PacienteResponsable $vinculo) => $this->presentar($vinculo, $paciente))->all();
    }

    public function pacientesDeCliente(int $idEmpresa, int $idCliente): array
    {
        $existe = Cliente::withoutGlobalScopes()
            ->where('id', $idCliente)
            ->where('id_empresa', $idEmpresa)
            ->exists();
        if (! $existe) {
            return [];
        }

        $vinculos = PacienteResponsable::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('id_cliente', $idCliente)
            ->whereNull('vigente_hasta')
            ->with([
                'paciente' => fn ($q) => $q->withoutGlobalScopes()->with([
                    'expediente' => fn ($expediente) => $expediente->withoutGlobalScopes(),
                ]),
            ])
            ->get();

        return $vinculos
            ->filter(fn (PacienteResponsable $vinculo) => $vinculo->paciente !== null)
            ->map(function (PacienteResponsable $vinculo) {
                $paciente = $vinculo->paciente;

                return [
                    'id' => $paciente->id,
                    'nombre' => $this->nombrePaciente($paciente),
                    'tipo' => $paciente->tipo,
                    'expediente' => $paciente->expediente?->numero,
                ];
            })
            ->unique('id')
            ->values()
            ->all();
    }

    public function vincular(Paciente $paciente, array $datos): PacienteResponsable
    {
        return DB::transaction(function () use ($paciente, $datos) {
            $rol = $this->rol($datos['rol'] ?? null);
            $esPaciente = filter_var($datos['es_el_paciente'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $idCliente = $datos['id_cliente'] ?? null;
            $idCliente = $idCliente === '' || $idCliente === null ? null : (int) $idCliente;
            $esPrincipal = $rol === 'principal' || filter_var($datos['es_principal'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($esPaciente && $paciente->tipo !== 'HUMANO') {
                throw ValidationException::withMessages([
                    'es_el_paciente' => 'Solo un paciente humano puede ser su propio responsable.',
                ]);
            }
            if ($esPaciente && $idCliente !== null) {
                throw ValidationException::withMessages([
                    'id_cliente' => 'Si el responsable es el paciente, no se vincula un cliente.',
                ]);
            }

            $idResponsable = null;
            if ($esPaciente) {
                $ya = PacienteResponsable::query()
                    ->where('id_paciente', $paciente->id)
                    ->whereNull('vigente_hasta')
                    ->where('es_el_paciente', true)
                    ->exists();
                if ($ya) {
                    throw ValidationException::withMessages([
                        'es_el_paciente' => 'Ese paciente ya está como responsable vigente.',
                    ]);
                }
            } elseif ($idCliente !== null) {
                $this->clienteDeLaEmpresa((int) $paciente->id_empresa, $idCliente);
                $ya = PacienteResponsable::query()
                    ->where('id_paciente', $paciente->id)
                    ->whereNull('vigente_hasta')
                    ->where('id_cliente', $idCliente)
                    ->exists();
                if ($ya) {
                    throw ValidationException::withMessages([
                        'id_cliente' => 'Ese cliente ya es responsable vigente de este paciente.',
                    ]);
                }
            } else {
                $nombre = PacienteReglas::vacio($datos['nombre'] ?? null);
                if ($nombre === null) {
                    throw ValidationException::withMessages(['nombre' => 'El nombre del responsable es obligatorio.']);
                }
                $documento = PacienteReglas::vacio($datos['documento'] ?? null);
                $persona = null;
                if ($documento !== null) {
                    $persona = Responsable::query()
                        ->where('id_empresa', $paciente->id_empresa)
                        ->where('documento', $documento)
                        ->first();
                }
                if ($persona === null) {
                    $persona = Responsable::create([
                        'id_empresa' => $paciente->id_empresa,
                        'nombre' => $nombre,
                        'documento' => $documento,
                        'telefono' => PacienteReglas::vacio($datos['telefono'] ?? null),
                        'correo' => PacienteReglas::vacio($datos['correo'] ?? null),
                    ]);
                }
                $idResponsable = $persona->id;
            }

            if ($esPrincipal) {
                $this->cederPrincipal((int) $paciente->id);
            }

            $vinculo = PacienteResponsable::create([
                'id_empresa' => $paciente->id_empresa,
                'id_paciente' => $paciente->id,
                'id_cliente' => $esPaciente ? null : $idCliente,
                'id_responsable' => $idResponsable,
                'rol' => $rol,
                'es_principal' => $esPrincipal,
                'es_el_paciente' => $esPaciente,
                'vigente_desde' => now()->toDateString(),
            ]);

            $this->afirmarAltaSiCerrada($paciente->fresh());

            return $vinculo;
        });
    }

    public function actualizar(Paciente $paciente, PacienteResponsable $vinculo, array $datos): PacienteResponsable
    {
        if ((int) $vinculo->id_paciente !== (int) $paciente->id || $vinculo->vigente_hasta !== null) {
            throw ValidationException::withMessages(['responsable' => 'Ese vínculo ya no está vigente.']);
        }

        return DB::transaction(function () use ($paciente, $vinculo, $datos) {
            $rol = $this->rol($datos['rol'] ?? $vinculo->rol);
            $esPrincipal = $rol === 'principal' || filter_var($datos['es_principal'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($esPrincipal) {
                $this->cederPrincipal((int) $paciente->id, $vinculo->id);
            }
            $vinculo->update([
                'rol' => $rol,
                'es_principal' => $esPrincipal,
            ]);
            $this->afirmarAltaSiCerrada($paciente->fresh());

            return $vinculo->fresh();
        });
    }

    public function desactivar(Paciente $paciente, PacienteResponsable $vinculo): PacienteResponsable
    {
        if ((int) $vinculo->id_paciente !== (int) $paciente->id || $vinculo->vigente_hasta !== null) {
            throw ValidationException::withMessages(['responsable' => 'Ese vínculo ya no está vigente.']);
        }

        return DB::transaction(function () use ($paciente, $vinculo) {
            $vinculo->update([
                'es_principal' => false,
                'vigente_hasta' => now()->toDateString(),
            ]);
            $this->afirmarAltaSiCerrada($paciente->fresh());

            return $vinculo->fresh();
        });
    }

    public function cerrarAlta(Paciente $paciente, bool $cerrada): Paciente
    {
        if (! $cerrada) {
            $paciente->update(['alta_cerrada' => false]);

            return $paciente->fresh();
        }

        $error = ResponsableReglas::puedeCerrarAlta(
            $paciente->tipo,
            EdadPaciente::anios($paciente->fecha_nacimiento?->format('Y-m-d')),
            $this->edadMenor((int) $paciente->id_empresa),
            $this->vigentesParaRegla((int) $paciente->id)
        );
        if ($error !== null) {
            throw ValidationException::withMessages(['alta' => $error]);
        }

        $paciente->update(['alta_cerrada' => true]);

        return $paciente->fresh();
    }

    private function presentar(PacienteResponsable $vinculo, Paciente $paciente): array
    {
        $cliente = null;
        if ($vinculo->id_cliente) {
            $cliente = Cliente::withoutGlobalScopes()
                ->where('id', $vinculo->id_cliente)
                ->where('id_empresa', $paciente->id_empresa)
                ->first(['id', 'tipo', 'nombre', 'apellido', 'nombre_empresa']);
        }
        $persona = $vinculo->id_responsable
            ? Responsable::query()->find($vinculo->id_responsable)
            : null;

        if ($vinculo->es_el_paciente) {
            $nombre = $this->nombrePaciente($paciente);
            $documento = $paciente->documento;
            $telefono = $paciente->telefono;
            $correo = $paciente->correo;
        } elseif ($cliente) {
            $nombre = $cliente->tipo === 'Empresa'
                ? ($cliente->nombre_empresa ?: trim($cliente->nombre.' '.$cliente->apellido))
                : trim($cliente->nombre.' '.$cliente->apellido);
            $documento = null;
            $telefono = null;
            $correo = null;
        } else {
            $nombre = $persona?->nombre;
            $documento = $persona?->documento;
            $telefono = $persona?->telefono;
            $correo = $persona?->correo;
        }

        return [
            'id' => $vinculo->id,
            'nombre' => $nombre,
            'documento' => $documento,
            'telefono' => $telefono,
            'correo' => $correo,
            'rol' => $vinculo->rol,
            'es_principal' => (bool) $vinculo->es_principal,
            'es_el_paciente' => (bool) $vinculo->es_el_paciente,
            'id_cliente' => $cliente?->id,
            'vigente_desde' => $vinculo->vigente_desde?->format('Y-m-d'),
            'vigente_hasta' => $vinculo->vigente_hasta?->format('Y-m-d'),
            'vigente' => $vinculo->vigente_hasta === null,
        ];
    }

    private function clienteDeLaEmpresa(int $idEmpresa, int $idCliente): void
    {
        $cliente = Cliente::withoutGlobalScopes()->where('id', $idCliente)->first(['id', 'id_empresa']);
        if ($cliente === null || (int) $cliente->id_empresa !== $idEmpresa) {
            throw ValidationException::withMessages([
                'id_cliente' => 'El cliente no pertenece a la empresa.',
            ]);
        }
    }

    private function cederPrincipal(int $idPaciente, ?int $exceptoId = null): void
    {
        PacienteResponsable::query()
            ->where('id_paciente', $idPaciente)
            ->whereNull('vigente_hasta')
            ->where('es_principal', true)
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->get()
            ->each(function (PacienteResponsable $anterior) {
                $anterior->update([
                    'es_principal' => false,
                    'rol' => $anterior->rol === 'principal' ? 'secundario' : $anterior->rol,
                ]);
            });
    }

    private function afirmarAltaSiCerrada(Paciente $paciente): void
    {
        if (! $paciente->alta_cerrada) {
            return;
        }

        $error = ResponsableReglas::puedeCerrarAlta(
            $paciente->tipo,
            EdadPaciente::anios($paciente->fecha_nacimiento?->format('Y-m-d')),
            $this->edadMenor((int) $paciente->id_empresa),
            $this->vigentesParaRegla((int) $paciente->id)
        );
        if ($error !== null) {
            throw ValidationException::withMessages(['alta' => $error]);
        }
    }

    private function vigentesParaRegla(int $idPaciente): array
    {
        return PacienteResponsable::query()
            ->where('id_paciente', $idPaciente)
            ->whereNull('vigente_hasta')
            ->get(['es_principal', 'es_el_paciente'])
            ->map(fn (PacienteResponsable $vinculo) => [
                'es_principal' => (bool) $vinculo->es_principal,
                'es_el_paciente' => (bool) $vinculo->es_el_paciente,
            ])
            ->all();
    }

    private function edadMenor(int $idEmpresa): int
    {
        $raw = DB::table('empresa_funcionalidades as ef')
            ->join('funcionalidades as f', 'f.id', '=', 'ef.id_funcionalidad')
            ->where('ef.id_empresa', $idEmpresa)
            ->where('f.slug', 'clinica-pacientes')
            ->value('ef.configuracion');

        $config = is_string($raw) ? json_decode($raw, true) : (array) $raw;
        $edad = (int) ($config['edad_menor'] ?? ResponsableReglas::EDAD_MENOR);

        return $edad > 0 ? $edad : ResponsableReglas::EDAD_MENOR;
    }

    private function rol(mixed $rol): string
    {
        $rol = (string) $rol;
        if (! in_array($rol, ResponsableReglas::ROLES, true)) {
            throw ValidationException::withMessages(['rol' => 'El rol del responsable no es válido.']);
        }

        return $rol;
    }

    private function nombrePaciente(Paciente $paciente): string
    {
        return $paciente->tipo === 'HUMANO'
            ? trim(($paciente->nombres ?? '').' '.($paciente->apellidos ?? ''))
            : (string) $paciente->nombre;
    }
}
